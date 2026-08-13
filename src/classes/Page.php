<?php

declare(strict_types=1);

class Page extends HTMLDocument
{
    // The description meta is capped near the length search engines actually
    // display, on a word boundary so it never ends mid-word. The real limit is
    // a pixel width rather than a character count, so this is a proxy for it.
    public const META_DESCRIPTION_MAX_LENGTH = 160;

    // The og/twitter twins get their own, longer cut. They are not bound by a
    // search snippet's width: Facebook takes an og:description up to 300
    // characters, and clipping a share to a search engine's limit threw away
    // most of a sentence for a reason that did not apply to it.
    public const SOCIAL_DESCRIPTION_MAX_LENGTH = 300;

    public ?string $title = null;
    public ?string $description = null;
    public ?string $image = null;
    public ?array $jsonLD = null;

    /**
     * When the thing this page is about was written, and last changed.
     *
     * A page that says when it is from is treated as a different kind of
     * result from one that does not - it can be sorted by date, shown with
     * one, and judged fresh or stale rather than undated. Set from whatever
     * the page is about (a Post hands over its own columns by name); left null
     * on a page that is not about anything dated, where claiming a date would
     * be worse than having none.
     */
    public ?string $createdAt = null;
    public ?string $editedAt = null;

    /**
     * Configuration this one page adds to the site-wide block - a setting that
     * only matters here, rather than one every page carries. The data of
     * whatever is being looked at does not belong here: the config travels as
     * a cookie, which every later request sends back up, so it rides on the
     * element that needs it instead (see ClientConfig).
     *
     * @var array<string, mixed>
     */
    public array $clientConfig = [];

    public ?string $bodyClass = null;

    // The head and body are assembled from the properties above the first time
    // the page renders, not at construction - so a caller can seed a Page from
    // an object (new Page($profileUser)) and adjust properties afterwards, and
    // the final values are the ones that get built. Guarded so a second render
    // (e.g. toDOM() then __toString()) doesn't append everything twice.
    private bool $assembled = false;

    public function toDOM(): \DOMElement
    {
        $this -> assembleHead();
        $this -> assembleBody();
        return parent::toDOM();
    }

    private function assembleHead(): void
    {
        $site_title = Config::get('siteTitle');
        if (!$this -> title) {
            $this -> title = $site_title;
        }
        $full_title = $this -> title === $site_title ? $site_title : $this -> title . ' - ' . $site_title;

        // Handed on whole. A search snippet and a shared card have different
        // room, and each cuts to its own at the point it is written - carrying
        // two pre-cut copies of one sentence around would only be two things
        // to keep in step.
        $description = $this -> description ?? SiteInfo::description();

        $url = current_url();

        $charset = new Meta;
        $charset -> charset = 'utf-8';
        $this -> addHeadContent($charset);

        $viewport = new Meta;
        $viewport -> name = 'viewport';
        $viewport -> content = 'width=device-width, initial-scale=1';
        $this -> addHeadContent($viewport);

        $title_element = new Title;
        $title_element -> contents[] = $full_title;
        $this -> addHeadContent($title_element);

        $favicon = new Link();
        $favicon -> rel = 'icon';
        $favicon -> href = Favicon::URL();
        $this -> addHeadContent($favicon);

        foreach (self::metaTags($full_title, $description, $this -> image, $url, $this -> createdAt, $this -> editedAt) as $meta) {
            $this -> addHeadContent($meta);
        }

        $json_ld = $this -> jsonLD ?? [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site_title,
            'url' => $url,
        ];

        $json_ld_script = new Script;
        $json_ld_script -> attributes['type'] = 'application/ld+json';
        $json_ld_script -> contents[] = safe_json_for_script($json_ld);
        $this -> addHeadContent($json_ld_script);

        // Base layer - loaded before the site's own sheets so local rules win
        // the cascade.
        $bootstrap = new Link;
        $bootstrap -> rel = 'stylesheet';
        $bootstrap -> href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
        // Pinned to this version's exact bytes, like the other CDN assets.
        $bootstrap -> attributes['integrity'] = 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH';
        $bootstrap -> attributes['crossorigin'] = 'anonymous';
        $this -> addHeadContent($bootstrap);

        // The stylesheet lives on googleapis but the font files it names live
        // on gstatic - an origin the browser only discovers once that CSS has
        // arrived. Warming it now runs the handshake during the CSS download
        // instead of after it.
        $font_origin = new Link;
        $font_origin -> rel = 'preconnect';
        $font_origin -> href = 'https://fonts.gstatic.com';
        $font_origin -> attributes['crossorigin'] = 'anonymous';
        $this -> addHeadContent($font_origin);

        $inter_font = new Link;
        $inter_font -> rel = 'stylesheet';
        $inter_font -> href = 'https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&display=swap';
        $this -> addHeadContent($inter_font);

        foreach (['themes', 'base', 'utilities', 'components', 'layout', 'mobile'] as $sheet) {
            $stylesheet = new Link;
            $stylesheet -> rel = 'stylesheet';
            $stylesheet -> href = ServerURL::absolute('/styles/' . $sheet . '.css');
            $this -> addHeadContent($stylesheet);
        }
    }

    private function assembleBody(): void
    {
        $this -> body -> class = $this -> bodyClass !== null ? 'PageBody ' . $this -> bodyClass : 'PageBody';

        // Everything the page added becomes the content of a <main> landmark,
        // with the nav left outside it as a landmark of its own. That gives
        // assistive tech a "skip to the content" target, which a page whose
        // content sat loose in <body> after the nav had no way to offer.
        $main = new Main;
        // What the skip link jumps to, and what it needs focusing on when it
        // does: a browser moves focus to the target of an in-page link only if
        // that target can hold focus.
        $main -> attributes['id'] = SkipLink::TARGET;
        $main -> attributes['tabindex'] = '-1';
        $main -> addContent(new PageTitle((string) $this -> title));

        // Before the page rather than on one page of it: somebody arrives at
        // whatever address was shared with them, and the offer is only worth
        // making where they land.
        if (LanguagePrompt::offer() !== null) {
            $main -> addContent(new LanguagePrompt());
        }

        $main -> addContents($this -> body -> contents);

        // The skip link leads, because a thing that lets somebody past the
        // navigation has to come before it.
        $this -> body -> contents = [new SkipLink, new MainNavigation, $main];

        $this -> addContent(new ScrollToTopButton);

        $main_module = new ModuleScript;
        $main_module -> src = ServerURL::absolute('/scripts/main.js');
        $this -> addContent($main_module);

        // Last of all, and only for the admin: what this page cost to build.
        // After the script tag rather than before it, so the figure covers as
        // much of the work as a thing inside the page can.
        if (PageGenerationTime::isForViewer()) {
            $this -> addContent(new PageGenerationTime());
        }
    }

    public function send(): void
    {
        ClientConfig::send($this -> clientConfig);

        parent::send();
    }

    /**
     * A stored timestamp as the date format the metadata wants, or null where
     * there is no timestamp to give. The columns are UTC, which is the clock
     * PHP runs on here, so the offset comes out right without being told.
     */
    private static function asISO8601(?string $timestamp): ?string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return null;
        }

        $moment = strtotime($timestamp);

        return $moment === false ? null : date('c', $moment);
    }

    /**
     * The description arrives whole and is cut where it is written: a search
     * snippet has less room than a shared card, and each tag knows its own.
     */
    protected static function metaTags(
        string $title,
        string $description,
        ?string $image,
        string $url,
        ?string $created_at = null,
        ?string $edited_at = null
    ): array {
        $tags = [];

        $description_tag = new Meta;
        $description_tag -> name = 'description';
        $description_tag -> content = truncate($description, self::META_DESCRIPTION_MAX_LENGTH);
        $tags[] = $description_tag;

        $published = self::asISO8601($created_at);
        $modified = self::asISO8601($edited_at);

        $og_pairs = [
            'og:title' => $title,
            'og:description' => truncate($description, self::SOCIAL_DESCRIPTION_MAX_LENGTH),
            // A dated page is an article, and the article:* properties below
            // only mean anything under that type.
            'og:type' => $published === null ? 'website' : 'article',
            'og:url' => $url,
        ];

        if ($published !== null) {
            $og_pairs['article:published_time'] = $published;
        }

        // Only when it really was changed - a modified time equal to the
        // published one says an edit happened that did not.
        if ($modified !== null) {
            $og_pairs['article:modified_time'] = $modified;
        }

        if ($image !== null) {
            $og_pairs['og:image'] = $image;
        }

        foreach ($og_pairs as $property => $content) {
            $tag = new Meta;
            $tag -> property = $property;
            $tag -> content = $content;
            $tags[] = $tag;
        }

        $twitter_pairs = [
            'twitter:card' => $image !== null ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => truncate($description, self::SOCIAL_DESCRIPTION_MAX_LENGTH),
        ];

        if ($image !== null) {
            $twitter_pairs['twitter:image'] = $image;
        }

        foreach ($twitter_pairs as $name => $content) {
            $tag = new Meta;
            $tag -> name = $name;
            $tag -> content = $content;
            $tags[] = $tag;
        }

        return $tags;
    }

}
