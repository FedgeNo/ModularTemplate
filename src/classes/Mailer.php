<?php

declare(strict_types=1);

/**
 * The SMTP relay settings (host/port/username/password/encryption), plus the
 * mail "from" address/name, all live in the Settings DB table, editable from
 * the Admin Settings page - live, no restart. Nothing reads them from
 * .env, so bin/install.php warns when it finds SMTP_HOST etc. or
 * MAIL_FROM_ADDRESS/MAIL_FROM_NAME set there: they would look like the live
 * configuration while having no effect on anything.
 */
class Mailer
{
    public const SMTP_HOST_SETTING = 'smtpHost';
    public const SMTP_PORT_SETTING = 'smtpPort';
    public const SMTP_USERNAME_SETTING = 'smtpUsername';
    public const SMTP_PASSWORD_SETTING = 'smtpPassword';
    public const SMTP_ENCRYPTION_SETTING = 'smtpEncryption';
    public const FROM_ADDRESS_SETTING = 'mailFromAddress';
    public const FROM_NAME_SETTING = 'mailFromName';

    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MICROSECONDS = 250000;

    // Sticky across every attempt() a single send() makes: true once ANY
    // attempt sees the destination server actively refuse this specific
    // recipient (RCPT TO rejected with a permanent reply), as opposed to our
    // own mail transport being unreachable or misconfigured. A later retry
    // that merely fails to connect must not erase an earlier attempt's
    // confirmed rejection - only send() resets this, between different
    // messages. Lets EmailVerification::sendFor() tell "mail is down" (safe
    // to auto-verify) apart from "this address doesn't accept mail" (an
    // attacker could engineer this on purpose, so it must not bypass
    // verification).
    private static bool $recipientRejected = false;

    /**
     * @param array<string, string> $extra_headers Headers this particular
     *   message needs on top of the ones every message carries - the
     *   unsubscribe pair on a digest, and nothing else so far. Never for
     *   transactional mail: a password reset is not something to opt out of.
     */
    public static function send(string $to_address, string $to_name, string $subject, string $text_body, string $html_body, array $extra_headers = []): bool
    {
        // Reset up front (not just in attempt(), which the early return below
        // skips entirely) - a previous call's stale true would otherwise leak
        // into recipientWasRejected() after a from-not-configured short-circuit,
        // which is clearly not a rejection.
        self::$recipientRejected = false;

        // No placeholder fallback (unlike mailFromName, a missing address
        // isn't cosmetic - there's nothing to send with). Rather than
        // silently mailing from an unset/broken address, don't attempt at
        // all - the admin will see this the moment they sign up themselves,
        // since that's the first email the site ever sends.
        if ((string) Settings::get(self::FROM_ADDRESS_SETTING, '') === '') {
            error_log('Mail not sent: no from-address configured (Admin Settings).');

            return false;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            if (self::attempt($to_address, $to_name, $subject, $text_body, $html_body, $extra_headers)) {
                return true;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep(self::RETRY_DELAY_MICROSECONDS);
            }
        }

        return false;
    }

    /**
     * The display name mail goes out under.
     *
     * Unlike the address, a missing name is cosmetic - the From header still
     * works without one - so this falls back rather than refusing to send.
     * Blank counts as missing: a default handed to Settings::get() only
     * applies where there is no row at all, so a stored empty string, which is
     * what a form saving the field without a value leaves behind, silently
     * beat the fallback and sent mail from nobody.
     */
    public static function fromName(): string
    {
        $stored = (string) Settings::get(self::FROM_NAME_SETTING, '');

        return $stored !== '' ? $stored : (string) Config::get('mailFromName');
    }

    public static function recipientWasRejected(): bool
    {
        return self::$recipientRejected;
    }

    private static function attempt(string $to_address, string $to_name, string $subject, string $text_body, string $html_body, array $extra_headers = []): bool
    {
        $from_address = (string) Settings::get(self::FROM_ADDRESS_SETTING, '');
        $from_name = self::fromName();

        $smtp_host = (string) Settings::get(self::SMTP_HOST_SETTING, '');

        $eol = chr(13) . chr(10);
        $boundary = 'boundary-' . bin2hex(random_bytes(16));

        $headers = [];
        $headers[] = 'From: ' . self::encodeHeader($from_name) . ' <' . $from_address . '>';
        $headers[] = 'Reply-To: ' . $from_address;
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . self::domainFromAddress($from_address) . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        // Whatever this one message adds. Folded in with no newline allowed
        // through: a value carrying one would end this header and start
        // another of the caller's choosing, which is header injection.
        foreach ($extra_headers as $name => $value) {
            $headers[] = $name . ': ' . str_replace([chr(13), chr(10)], '', $value);
        }

        $body = '--' . $boundary . $eol
            . 'Content-Type: text/plain; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: 8bit' . $eol . $eol
            . $text_body . $eol . $eol
            . '--' . $boundary . $eol
            . 'Content-Type: text/html; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: 8bit' . $eol . $eol
            . $html_body . $eol . $eol
            . '--' . $boundary . '--';

        $to = self::encodeHeader($to_name) . ' <' . $to_address . '>';
        $encoded_subject = self::encodeHeader($subject);

        // A configured SMTP relay takes precedence over PHP's mail() (the
        // local sendmail handoff, which on a typical VPS has no reputation and
        // lands in spam - see README's deliverability section).
        if ($smtp_host !== '') {
            $message = 'To: ' . $to . $eol
                . 'Subject: ' . $encoded_subject . $eol
                . implode($eol, $headers) . $eol . $eol
                . $body;

            return self::sendViaSMTP(
                $smtp_host,
                (int) Settings::get(self::SMTP_PORT_SETTING, '587'),
                (string) Settings::get(self::SMTP_USERNAME_SETTING, ''),
                (string) Settings::get(self::SMTP_PASSWORD_SETTING, ''),
                (string) Settings::get(self::SMTP_ENCRYPTION_SETTING, 'tls'),
                $from_address,
                $to_address,
                $message
            );
        }

        return mail($to, $encoded_subject, $body, implode($eol, $headers));
    }

    /**
     * A minimal SMTP client (no dependencies, same spirit as the hand-rolled
     * WebSocket daemon): EHLO, optional STARTTLS, optional AUTH LOGIN, one
     * MAIL FROM/RCPT TO/DATA exchange, QUIT. Returns false (after logging the
     * server's complaint) on any unexpected reply, letting send()'s retry
     * loop and the callers' failure handling take over.
     */
    private static function sendViaSMTP(
        string $smtp_host,
        int $smtp_port,
        string $smtp_username,
        string $smtp_password,
        string $smtp_encryption,
        string $from_address,
        string $to_address,
        string $message
    ): bool {
        $eol = chr(13) . chr(10);
        $timeout_seconds = 10;

        $transport = $smtp_encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $transport . $smtp_host . ':' . $smtp_port,
            $error_code,
            $error_message,
            $timeout_seconds
        );

        if ($socket === false) {
            error_log('SMTP connection to ' . $smtp_host . ':' . $smtp_port . ' failed: ' . $error_message);

            return false;
        }

        stream_set_timeout($socket, $timeout_seconds);

        $expect = function (string $expected_prefix, ?string &$reply_code = null) use ($socket): bool {
            // Replies can span lines ("250-..." continuations); the final line
            // has a space after the code.
            do {
                $line = fgets($socket);

                if ($line === false) {
                    error_log('SMTP connection dropped mid-reply');
                    $reply_code = null;

                    return false;
                }
            } while (isset($line[3]) && $line[3] === '-');

            $reply_code = substr($line, 0, 3);

            if (!str_starts_with($line, $expected_prefix)) {
                error_log('SMTP expected ' . $expected_prefix . ' but got: ' . trim($line));

                return false;
            }

            return true;
        };

        $say = function (string $command) use ($socket, $eol): void {
            fwrite($socket, $command . $eol);
        };

        $hello_name = self::domainFromAddress($from_address);

        $conversation = function () use ($smtp_encryption, $smtp_username, $smtp_password, $from_address, $to_address, $message, $expect, $say, $socket, $eol, $hello_name): bool {
            if (!$expect('220')) {
                return false;
            }

            $say('EHLO ' . $hello_name);

            if (!$expect('250')) {
                return false;
            }

            if ($smtp_encryption === 'tls') {
                $say('STARTTLS');

                if (!$expect('220')) {
                    return false;
                }

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    error_log('SMTP STARTTLS negotiation failed');

                    return false;
                }

                // The session resets after STARTTLS - greet again.
                $say('EHLO ' . $hello_name);

                if (!$expect('250')) {
                    return false;
                }
            }

            if ($smtp_username !== '') {
                $say('AUTH LOGIN');

                if (!$expect('334')) {
                    return false;
                }

                $say(base64_encode($smtp_username));

                if (!$expect('334')) {
                    return false;
                }

                $say(base64_encode($smtp_password));

                if (!$expect('235')) {
                    return false;
                }
            }

            $say('MAIL FROM:<' . $from_address . '>');

            if (!$expect('250')) {
                return false;
            }

            $say('RCPT TO:<' . $to_address . '>');

            if (!$expect('25', $rcpt_reply_code)) {
                // A 4xx reply is transient (greylisting, "try again later",
                // a temporary local problem on the destination server) - the
                // address itself isn't necessarily bad, so this must not be
                // treated as a permanent rejection of the recipient. A null
                // reply code means no reply was read at all (a dropped
                // connection - a transport failure, not the server refusing
                // this recipient), so that must not set it either. Only an
                // actually-read 5xx (or other non-4xx) reply means the
                // destination server is genuinely refusing this address.
                if ($rcpt_reply_code !== null && !str_starts_with($rcpt_reply_code, '4')) {
                    self::$recipientRejected = true;
                }

                return false;
            }

            $say('DATA');

            if (!$expect('354')) {
                return false;
            }

            // Dot-stuffing: a line consisting of a single '.' would end the
            // message early, so any line starting with '.' gets it doubled.
            $stuffed = preg_replace('/^\./m', '..', $message);

            fwrite($socket, $stuffed . $eol . '.' . $eol);

            if (!$expect('250')) {
                return false;
            }

            $say('QUIT');

            // The message is already accepted (the 250 above) so a missing or
            // odd QUIT reply doesn't change the outcome - but read the 221 the
            // server sends before we close, so the conversation ends cleanly
            // rather than dropping the socket mid-reply.
            $expect('221');

            return true;
        };

        $sent = $conversation();
        fclose($socket);

        return $sent;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function domainFromAddress(string $address): string
    {
        $parts = explode('@', $address);

        return $parts[1] ?? 'localhost';
    }
}
