<?php

declare(strict_types=1);

class MainNavigation extends Nav
{
    public ?string $class = 'MainNavigation';

    public function toDOM(): \DOMElement
    {
        // Checkbox-hack toggle: the hamburger <label> flips this hidden checkbox,
        // and the CSS below the nav breakpoint reveals the stacked menu while
        // it's checked - so mobile navigation is pure CSS, no JS. Above the
        // breakpoint both are hidden and the desktop hover-flyouts take over.
        $toggle = new CheckboxInput();
        $toggle -> id = 'NavToggle';
        $toggle -> class = 'NavToggle';
        // The control itself is the checkbox, not the bars that show it - so
        // it is the checkbox that has to say what it is. Without this a screen
        // reader reaches an unnamed checkbox, the label being three empty
        // spans, and the hamburger's own aria-label is not reliably borrowed.
        $toggle -> attributes['aria-label'] = (string) (Strings::for(self::class)['toggleLabel'] ?? '');
        $this -> addContent($toggle);

        $hamburger = new Label();
        $hamburger -> for = 'NavToggle';
        $hamburger -> class = 'NavHamburger';

        for ($i = 0; $i < 3; $i++) {
            $bar = new NavHamburgerBar();
            $hamburger -> addContent($bar);
        }

        $this -> addContent($hamburger);

        $brand = new NavBrand(ServerURL::absolute('/'), Config::get('siteTitle'));

        $site_links = new MainNavigationSiteLinks();

        $account_links = new MainNavigationAccountLinks();

        // Desktop: a hover-flyout of the main menu hangs off the brand. Mobile:
        // the same links render inline inside the toggled menu - one set of link
        // instances, no duplicate mobile list.
        $this -> addContent(new NavDropdown($brand, $this -> mainMenuLinks()));

        if (Auth::check()) {
            $current_user = Auth::user();

            $account_label = new NavAccountLabel();
            $account_label -> addContent(str_replace(
                '{name}',
                (string) ($current_user -> title ?: $current_user -> slug),
                (string) (Strings::for(self::class)['loggedInAs'] ?? '')
            ));

            $account_trigger = new Anchor(ServerURL::absolute('/user-settings'));
            $account_trigger -> addContent($account_label);

            $account_links -> addContent(new NavDropdown($account_trigger, $this -> accountMenuLinks()));
        } else {
            // Logged-out visitors get Log in / Sign up as plain links.
            $account_links -> addContents($this -> accountMenuLinks());
        }

        $this -> addContent($site_links);
        $this -> addContent($account_links);

        return parent::toDOM();
    }

    /**
     * The brand/main-menu links.
     *
     * @return Anchor[]
     */
    private function mainMenuLinks(): array
    {
        $words = Strings::for(self::class);

        return [
            new Anchor(ServerURL::absolute('/about'), (string) ($words['about'] ?? '')),
        ];
    }

    /**
     * The account-menu links.
     *
     * @return HTMLObject[]
     */
    private function accountMenuLinks(): array
    {
        $words = Strings::for(self::class);

        if (!Auth::check()) {
            return [
                new Anchor(ServerURL::absolute('/login'), (string) ($words['login'] ?? '')),
                new Anchor(ServerURL::absolute('/signup'), (string) ($words['signup'] ?? '')),
            ];
        }

        // Settings pages together, and logging out last - it is the one thing
        // here that ends the session rather than opening something, and it
        // does not want to be in the middle of a list being scanned.
        $links = [
            new Anchor(ServerURL::absolute('/user-settings'), (string) ($words['userSettings'] ?? '')),
        ];

        // Site-wide settings are the primary admin's alone.
        if (Auth::id() === 1) {
            $links[] = new Anchor(ServerURL::absolute('/admin/settings'), (string) ($words['adminSettings'] ?? ''));
        }

        $links[] = new LogoutForm();

        return $links;
    }
}
