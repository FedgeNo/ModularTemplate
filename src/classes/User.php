<?php

declare(strict_types=1);

class User extends Div implements \JsonSerializable
{
    /** Longest username a person on this site can have. */
    public const MAX_USERNAME_LENGTH = 32;

    /**
     * Reduces whatever was typed to the username that would actually be
     * stored: lowercase, only letters/digits/underscore, capped. Disallowed
     * characters are dropped, so a name never contains a character the person
     * didn't type.
     *
     * The one place this is decided, so sign-up and the availability check
     * can't disagree about what a given input means.
     */
    public static function normaliseUsername(string $raw): string
    {
        return substr((string) preg_replace('/[^a-z0-9_]/', '', strtolower(trim($raw))), 0, self::MAX_USERNAME_LENGTH);
    }

    public ?string $class = 'User';

    public ?int $userId = null;
    public ?string $slug = null;
    public ?string $email = null;
    /**
     * Private so a hash can only ever be checked or replaced, never read out
     * of a User and copied somewhere it could be cracked offline. mysqli's
     * object hydration fills it regardless of visibility, so loading a user
     * still works.
     */
    private ?string $passwordHash = null;
    public ?string $title = null;
    public ?string $createdAt = null;
    public int $banned = 0;
    public ?string $banReason = null;
    public int $isMod = 0;
    public int $verified = 0;
    public ?string $locale = null;
    public string $theme = 'system';
    public ?string $lastSeen = null;
    public int $sessionVersion = 0;

    public function verifyPassword(string $password): bool
    {
        return $this -> passwordHash !== null && password_verify($password, $this -> passwordHash);
    }

    public function passwordNeedsRehash(): bool
    {
        return $this -> passwordHash !== null && password_needs_rehash($this -> passwordHash, PASSWORD_DEFAULT);
    }

    /**
     * Keeps a loaded User in step with a hash the caller has already written
     * to the row.
     */
    public function setPasswordHash(string $hash): void
    {
        $this -> passwordHash = $hash;
    }

    /**
     * What a User is when it's encoded as JSON. Named explicitly rather than
     * left to json_encode's default, which would publish every public property
     * - including the password hash, the email and the session version - to
     * anything that ever encodes one.
     */
    public function jsonSerialize(): array
    {
        return [
            'userId' => (int) $this -> userId,
            'slug' => $this -> slug,
            'title' => $this -> title,
            'createdAt' => $this -> createdAt,
            'isMod' => (bool) $this -> isMod,
        ];
    }

    public static function fromRow(array $row): static
    {
        $user = new static();

        foreach ($row as $key => $value) {
            $user -> $key = $value;
        }

        return $user;
    }

    public static function load(int $user_id): ?self
    {
        return self::loadMany([$user_id])[$user_id] ?? null;
    }

    /**
     * @param int[] $user_ids
     * @return array<int, self> userId => User
     */
    public static function loadMany(array $user_ids): array
    {
        if ($user_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($user_ids), '?'));

        $rows = DB::rows('
SELECT *
    FROM `Users`
    WHERE `userId` IN (' . $placeholders . ')
', 'User', str_repeat('i', count($user_ids)), ...$user_ids);

        $users = [];

        foreach ($rows as $user) {
            $users[(int) $user -> userId] = $user;
        }

        return $users;
    }

    public static function loadByUsername(string $username): ?self
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `slug` = ?
', 'User', 's', $username);
    }

    /**
     * How stale the stored mark may be before it is worth writing again.
     *
     * Every request from somebody signed in could write one, and on a page
     * with any reading in it that is a write per click for a figure nobody
     * needs to the second. Five minutes is finer than any question asked of
     * it - who was here today, who this week - and turns a long read into a
     * single write.
     */
    private const SEEN_AGAIN_AFTER_SECONDS = 300;

    /**
     * Notes that this member is here now, if it has been long enough since
     * the last time.
     *
     * Called from init.php on every request with somebody behind it. Whether
     * the mark is stale is judged against the row already loaded, so a
     * request that writes nothing also reads nothing extra.
     */
    public static function seen(self $user): void
    {
        if ($user -> lastSeen !== null && strtotime($user -> lastSeen) > time() - self::SEEN_AGAIN_AFTER_SECONDS) {
            return;
        }

        DB::run('
UPDATE `Users`
    SET `lastSeen` = NOW()
    WHERE `userId` = ?
', 'i', (int) $user -> userId);

        $user -> lastSeen = date('Y-m-d H:i:s');
    }

    /**
     * Invalidates every existing session for the user by bumping their
     * sessionVersion - a session records the version it was created under and
     * init.php logs out any session whose recorded version no longer matches.
     * Called on password change/reset so a stolen or forgotten-open session
     * doesn't outlive the credentials that created it. Returns the new
     * version so the calling session can adopt it and stay logged in.
     */
    public static function bumpSessionVersion(int $user_id): int
    {
        DB::run('
UPDATE `Users`
    SET `sessionVersion` = `sessionVersion` + 1
    WHERE `userId` = ?
', 'i', $user_id);

        $user = DB::row('
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);

        return $user ?-> sessionVersion ?? 0;
    }
}
