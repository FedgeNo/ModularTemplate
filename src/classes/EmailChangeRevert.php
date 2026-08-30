<?php

declare(strict_types=1);

/**
 * The "wasn't you?" safety net for a changed email address: notifies the OLD
 * address (never the new one - the point is reaching whoever actually owned
 * the account before the change) with a link that reverts the email back and
 * signs out every device. No login is required to consume the link - the
 * whole point is recovering an account that may otherwise be unreachable
 * (the new address could be one an attacker controls), so the token itself
 * is the proof of authorization, same as password reset.
 */
class EmailChangeRevert
{
    public static function sendFor(User $user, string $previous_email): void
    {
        self::sendMail($user, $previous_email, self::create((int) $user -> userId, $previous_email));
    }

    /** Sends a token created separately, so callers can create it transactionally. */
    public static function sendMail(User $user, string $previous_email, string $token): void
    {
        $revert_url = ServerURL::absolute('/revert-email?token=' . $token);

        $name = $user -> title ?: $user -> slug;

        $text_body = 'Hi ' . $name . ',

The email address on your ' . Config::get('siteTitle') . ' account was just changed to ' . $user -> email . '.

If this was you, no action is needed.

If this was NOT you, click this link to revert the change and sign every device out of your account:
' . $revert_url . '

This link expires in 30 days.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>The email address on your ' . Config::get('siteTitle') . ' account was just changed to ' . htmlspecialchars($user -> email) . '.</p>'
            . '<p>If this was you, no action is needed.</p>'
            . '<p><strong>If this was NOT you</strong>, click the link below to revert the change and sign every device out of your account:</p>'
            . '<p><a href="' . htmlspecialchars($revert_url) . '">Revert Email Change</a></p>'
            . '<p>This link expires in 30 days.</p>';

        $sent = Mailer::send($previous_email, $name, 'Your email address was changed', $text_body, $html_body);

        if (!$sent) {
            // The old address is the one place a hijack notice could reach
            // its real owner - if that itself can't be delivered, the admin
            // needs to know the mailer is down.
            error_log('Email-change revert notice could not be sent for userId ' . (int) $user -> userId);
        }
    }

    /**
     * Whether $email is the previous address of some outstanding (unexpired,
     * unconsumed) revert - reserved for its rightful owner to reclaim, so
     * nobody else (a new signup, or another account's email change) can be
     * handed it in the meantime.
     */
    public static function isReserved(string $email): bool
    {
        $stmt = DB::run('
SELECT 1
    FROM `EmailChangeReverts`
    WHERE `previousEmail` = ? AND `expiresAt` > NOW()
', 's', $email);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * Reverts the email change the token was issued for: restores the
     * previous (already-verified) address, signs out every session, and
     * clears any now-moot pending tokens (the revert token itself, any
     * others for the same user, and any pending verification for the
     * abandoned new address). Returns false for an invalid or expired token.
     */
    public static function consume(string $token): bool
    {
        $token_hash = hash('sha256', $token);

        $revert = DB::row('
SELECT `userId`, `previousEmail`
    FROM `EmailChangeReverts`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 'EmailChangeRevertData', 's', $token_hash);

        if ($revert === null) {
            return false;
        }

        $user_id = (int) $revert -> userId;

        try {
            return DB::transaction(function () use ($token_hash, $user_id, $revert): bool {
                // Claim before changing the account. Only one request racing
                // this token can delete exactly one row.
                $claim = DB::run('
DELETE
    FROM `EmailChangeReverts`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 's', $token_hash);

                if (mysqli_stmt_affected_rows($claim) !== 1) {
                    return false;
                }

                // The previous address was verified before the change.
                $verified = 1;

                DB::run('
UPDATE `Users`
    SET `email` = ?, `verified` = ?
    WHERE `userId` = ?
', 'sii', $revert -> previousEmail, $verified, $user_id);

                User::bumpSessionVersion($user_id);

                DB::run('
DELETE
    FROM `EmailVerifications`
    WHERE `userId` = ?
', 'i', $user_id);

                DB::run('
DELETE
    FROM `EmailChangeReverts`
    WHERE `userId` = ?
', 'i', $user_id);

                return true;
            });
        } catch (\mysqli_sql_exception $exception) {
            // A reserved address should remain free, but the unique index is
            // still the authority if another writer races the restore.
            return false;
        }
    }

    public static function create(int $user_id, string $previous_email): string
    {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        // Unlike a password reset or email verification link (both expected
        // to be used within minutes), this one is a safety net someone might
        // not see for a while - if their inbox itself was part of what got
        // compromised, or they just don't check that address often. A long
        // window matters more here than the usual short-lived-token hygiene.
        $expiry_days = 30;

        DB::run('
DELETE
    FROM `EmailChangeReverts`
    WHERE `expiresAt` <= NOW()
');

        // previousEmail is UNIQUE, so at most one outstanding revert ever
        // reserves a given address - and since Users.email is itself unique,
        // only one account can hold (and so change away from) an address at a
        // time. The upsert makes reserving an address atomic against a
        // concurrent reservation of the same one, and overwrites a lingering
        // expired row for it in place.
        DB::run('
INSERT INTO `EmailChangeReverts` (`userId`, `previousEmail`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, ?, NOW() + INTERVAL ? DAY)
    ON DUPLICATE KEY UPDATE `userId` = VALUES(`userId`), `tokenHash` = VALUES(`tokenHash`), `expiresAt` = VALUES(`expiresAt`), `createdAt` = NOW()
', 'issi', $user_id, $previous_email, $token_hash, $expiry_days);

        return $token;
    }
}
