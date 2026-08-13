<?php

declare(strict_types=1);

class PasswordReset
{
    public static function sendFor(User $user): void
    {
        $token = self::create((int) $user -> userId);

        $reset_url = ServerURL::absolute('/reset-password?token=' . $token);

        $name = $user -> title ?: $user -> slug;

        $text_body = 'Hi ' . $name . ',

Someone (hopefully you) requested a password reset. Visit this link to choose a new password:
' . $reset_url . '

This link expires in 1 hour. If you did not request this, you can ignore this email.';

        $html_body = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
            . '<p>Someone (hopefully you) requested a password reset. Click the link below to choose a new password:</p>'
            . '<p><a href="' . htmlspecialchars($reset_url) . '">Reset Password</a></p>'
            . '<p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>';

        $sent = Mailer::send($user -> email, $name, 'Reset your password', $text_body, $html_body);

        if (!$sent) {
            // Unlike sign-up, a failed reset can't just proceed - without the
            // emailed link there's nothing to let the user through with.
            error_log('Password reset email could not be sent for userId ' . (int) $user -> userId);
        }
    }

    public static function verify(string $token): ?int
    {
        $token_hash = hash('sha256', $token);

        $reset = DB::row('
SELECT `userId`
    FROM `PasswordResets`
    WHERE `tokenHash` = ? AND `expiresAt` > NOW()
', 'PasswordResetData', 's', $token_hash);

        return $reset !== null ? (int) $reset -> userId : null;
    }

    public static function consume(string $token, string $new_password): bool
    {
        $user_id = self::verify($token);

        if ($user_id === null) {
            return false;
        }

        $hash = password_hash($new_password, PASSWORD_DEFAULT);

        DB::run('
UPDATE `Users`
    SET `passwordHash` = ?
    WHERE `userId` = ?
', 'si', $hash, $user_id);

        // The old password's sessions die with it - whoever prompted the
        // reset may not be the only one logged in.
        User::bumpSessionVersion($user_id);

        $token_hash = hash('sha256', $token);

        DB::run('
DELETE
    FROM `PasswordResets`
    WHERE `tokenHash` = ?
', 's', $token_hash);

        return true;
    }

    private static function create(int $user_id): string
    {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expiry_hours = 1;

        DB::run('
DELETE
    FROM `PasswordResets`
    WHERE `expiresAt` <= NOW()
');

        DB::run('
INSERT INTO `PasswordResets` (`userId`, `tokenHash`, `expiresAt`)
    VALUES (?, ?, NOW() + INTERVAL ? HOUR)
', 'isi', $user_id, $token_hash, $expiry_hours);

        return $token;
    }
}
