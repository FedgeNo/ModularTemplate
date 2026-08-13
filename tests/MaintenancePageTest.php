<?php

declare(strict_types=1);

/**
 * The page shown when the database cannot be served against - a schema behind
 * the code, or a version lookup that failed. What matters about it is what it
 * does not do: every other page is assembled out of the database, and this one
 * is shown because that is exactly what cannot be relied on.
 */
class MaintenancePageTest extends TestCase
{
    private function documentFor(MaintenancePage $page): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        return (string) $page;
    }

    public function testTheUpgradeNoticeSaysWhatIsHappening(): void
    {
        $html = $this -> documentFor(MaintenancePage::saying(503, 'Upgrade In Progress', 'The site is being upgraded and will be back shortly.'));

        $this -> assertTrue(str_contains($html, 'Upgrade In Progress'), 'the title');
        $this -> assertTrue(str_contains($html, 'The site is being upgraded and will be back shortly.'), 'the message');
    }

    /**
     * The whole point of the class. A page rendered from the render system's
     * usual parts would ask for the site's icon, its description, who is
     * reading and what theme they chose - each a query against a database this
     * page exists to say nothing can be asked of.
     */
    public function testItReadsNothingFromTheDatabase(): void
    {
        $before = DB::queryCount();

        $this -> documentFor(MaintenancePage::saying(503, 'Upgrade In Progress', 'Back shortly.'));

        $this -> assertSame($before, DB::queryCount(), 'queries run while rendering the maintenance page');
    }

    /** The navigation is a query too - it counts a member's unseen notifications. */
    public function testItCarriesNoNavigation(): void
    {
        $html = $this -> documentFor(MaintenancePage::saying(503, 'Upgrade In Progress', 'Back shortly.'));

        $this -> assertFalse(str_contains($html, 'MainNavigation'), 'navigation on a page that cannot read the database');
        $this -> assertFalse(str_contains($html, 'scripts/main.js'), 'a script that expects a configuration it was not sent');
    }

    /** Its words are in the code, in English, and it says so. */
    public function testItDeclaresTheLanguageItIsWrittenIn(): void
    {
        $html = $this -> documentFor(MaintenancePage::saying(503, 'Upgrade In Progress', 'Back shortly.'));

        $this -> assertTrue(str_contains($html, 'lang="en"'));
    }

    /** Static files, so the page still arrives looking like the site it belongs to. */
    public function testItStillWearsTheSitesStylesheets(): void
    {
        $html = $this -> documentFor(MaintenancePage::saying(503, 'Upgrade In Progress', 'Back shortly.'));

        foreach (['themes', 'base', 'utilities', 'components', 'layout', 'mobile'] as $sheet) {
            $this -> assertTrue(str_contains($html, '/styles/' . $sheet . '.css'), $sheet);
        }
    }
}
