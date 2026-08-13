<?php

declare(strict_types=1);

/**
 * The line at the foot of a page telling the admin what it cost to build.
 *
 * It is for one person, so the thing worth guarding is that nobody else is
 * shown it - a build time and a query count are not secrets, but they are
 * furniture in everybody else's way and an invitation to probe.
 */
class PageGenerationTimeTest extends DatabaseTestCase
{
    private function asViewer(?int $user_id, callable $body): mixed
    {
        $was = $_SESSION['userId'] ?? null;

        if ($user_id === null) {
            unset($_SESSION['userId']);
        } else {
            $_SESSION['userId'] = $user_id;
        }

        try {
            return $body();
        } finally {
            if ($was === null) {
                unset($_SESSION['userId']);
            } else {
                $_SESSION['userId'] = $was;
            }
        }
    }

    private function markup(): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = new PageGenerationTime() -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return (string) HTMLObject::currentDocument() -> saveHTML($element);
    }

    public function testOnlyTheAdminIsShownIt(): void
    {
        // init.php defines this on a real request; the suite does not load it.
        if (!defined('APP_REQUEST_STARTED_AT')) {
            define('APP_REQUEST_STARTED_AT', microtime(true));
        }

        $this -> assertTrue($this -> asViewer(1, static fn (): bool => PageGenerationTime::isForViewer()));
        $this -> assertFalse($this -> asViewer(self::createUser(), static fn (): bool => PageGenerationTime::isForViewer()));
        $this -> assertFalse($this -> asViewer(null, static fn (): bool => PageGenerationTime::isForViewer()));
    }

    public function testItSaysBothHowLongAndHowManyQueries(): void
    {
        if (!defined('APP_REQUEST_STARTED_AT')) {
            define('APP_REQUEST_STARTED_AT', microtime(true));
        }

        $markup = $this -> markup();

        $this -> assertTrue(str_contains($markup, 'Built in'));
        $this -> assertTrue(str_contains($markup, 'ms'));
        $this -> assertTrue(str_contains($markup, 'quer'), 'the query count is the half that creeps');
    }

    /**
     * The count has to actually follow the work, or the number is worse than
     * no number - it would say a page grew no queries while it grew twenty.
     */
    public function testEveryStatementIsCounted(): void
    {
        $before = DB::queryCount();

        DB::run('
SELECT 1
');
        DB::run('
SELECT 2
');

        $this -> assertSame($before + 2, DB::queryCount());
    }
}
