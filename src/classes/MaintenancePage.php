<?php

declare(strict_types=1);

/**
 * The page served when the database is not in a state this code can serve
 * against: a schema the installer has not caught up with, or a version lookup
 * that failed outright.
 *
 * Its own document rather than a Page, because a Page is assembled out of the
 * database - the site's icon, its description, who is reading, what theme they
 * chose, how many notifications they have - and this is the one page that is
 * shown precisely because none of that can be asked for. Nothing here reads
 * anything: the title comes from the configuration file and the stylesheets are
 * static files, so it still arrives looking like the site it belongs to.
 */
class MaintenancePage extends HTMLDocument
{
    public static function saying(int $status_code, string $title, string $message): self
    {
        $page = new self();
        $page -> statusCode = $status_code;
        $page -> title = $title;
        $page -> message = $message;

        return $page;
    }

    public int $statusCode = 503;

    public string $title = '';

    public string $message = '';

    public function send(): void
    {
        http_response_code($this -> statusCode);

        // Only where there is something to come back to: an upgrade ends, and
        // a database that cannot be reached is not on a schedule.
        if ($this -> statusCode === 503) {
            header('Retry-After: 60');
        }

        parent::send();
    }

    public function toDOM(): \DOMElement
    {
        $charset = new Meta;
        $charset -> charset = 'utf-8';
        $this -> addHeadContent($charset);

        $viewport = new Meta;
        $viewport -> name = 'viewport';
        $viewport -> content = 'width=device-width, initial-scale=1';
        $this -> addHeadContent($viewport);

        $site_title = (string) Config::get('siteTitle');

        $title_element = new Title;
        $title_element -> contents[] = $site_title === '' ? $this -> title : $this -> title . ' - ' . $site_title;
        $this -> addHeadContent($title_element);

        foreach (['themes', 'base', 'utilities', 'components', 'layout', 'mobile'] as $sheet) {
            $stylesheet = new Link;
            $stylesheet -> rel = 'stylesheet';
            $stylesheet -> href = ServerURL::absolute('/styles/' . $sheet . '.css');
            $this -> addHeadContent($stylesheet);
        }

        $this -> body -> class = 'PageBody';

        $main = new Main;

        $heading = new Heading1;
        $heading -> contents[] = $this -> title;
        $main -> addContent($heading);
        $main -> addContent(new Paragraph($this -> message));

        $this -> body -> contents = [$main];

        return parent::toDOM();
    }

    /** Nothing: the theme a reader chose is a column in a table this cannot read. */
    protected function applyReaderTheme(): void
    {
    }

    /**
     * English, which is what this page is written in - the language a reader
     * chose is another column in that same table, and its words are here in the
     * code rather than translated.
     */
    protected function documentLanguage(): string
    {
        return Strings::SOURCE_LOCALE;
    }
}
