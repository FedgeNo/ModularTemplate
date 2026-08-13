<?php

declare(strict_types=1);

class SetupForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        // Everything guessable is pre-filled: the site URL from how this
        // page is being visited right now, the database fields from the
        // standard local-MySQL defaults. All of it stays editable - these
        // are starting points the installing admin reviews, not decisions.
        // The URL is always prefilled as https:// regardless of how the setup
        // page itself was reached - an http URL is rejected on submit (HTTPS
        // is required), so prefilling the reached-by protocol would just bake
        // in an error.
        $current_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'example.com');

        $site_url_label = (string) ($words['siteURLLabel'] ?? '');
        $site_url = new InputField('siteURL', $site_url_label, 'text', $site_url_label, 255);
        $site_url -> value = $current_url;

        // Deliberately not prefilled, unlike its neighbours: this is the site's
        // own name, nothing about the request can guess it, and offering one
        // would just be putting a name in the admin's mouth. Required on submit.
        $site_title_label = (string) ($words['siteTitleLabel'] ?? '');
        $site_title = new InputField('siteTitle', $site_title_label, 'text', $site_title_label, 100);

        $mail_from_address_label = (string) ($words['mailFromAddressLabel'] ?? '');
        $mail_from_address = new InputField('mailFromAddress', $mail_from_address_label, 'email', $mail_from_address_label, 255);
        $mail_from_address -> value = 'noreply@' . (parse_url($current_url, PHP_URL_HOST) ?: 'example.com');

        $site_fields = new Fieldset((string) ($words['siteLegend'] ?? ''));
        $site_fields -> addContent($site_url);
        $site_fields -> addContent($site_title);
        $site_fields -> addContent($mail_from_address);

        // ServerName/UseCanonicalName are proven live (a forged-Host-header
        // request against the entered site URL) wherever possible - this
        // checkbox is only consulted as a fallback when that live test comes
        // back inconclusive, mirroring bin/install.php's SERVERNAME_CONFIRMED
        // override for the same case.
        $current_host = (string) (parse_url($current_url, PHP_URL_HOST) ?: 'your-domain');
        $server_name_confirmed_label = str_replace(
            '{host}',
            $current_host,
            (string) ($words['serverNameConfirmedLabel'] ?? '')
        );
        $server_name_confirmed = new CheckboxField('serverNameConfirmed', $server_name_confirmed_label);
        $site_fields -> addContent($server_name_confirmed);

        $this -> contents[] = $site_fields;

        $db_host_label = (string) ($words['databaseHostLabel'] ?? '');
        $db_host = new InputField('DBHost', $db_host_label, 'text', $db_host_label, 255);
        $db_host -> value = '127.0.0.1';

        $db_port_label = (string) ($words['databasePortLabel'] ?? '');
        $db_port = new InputField('DBPort', $db_port_label, 'text', $db_port_label, 5);
        $db_port -> value = '3306';

        $db_database_label = (string) ($words['databaseNameLabel'] ?? '');
        $db_database = new InputField('DBDatabase', $db_database_label, 'text', $db_database_label, 64);
        $db_database -> value = 'app';

        $admin_username_label = (string) ($words['databaseAdminUsernameLabel'] ?? '');
        $admin_username = new InputField('adminUsername', $admin_username_label, 'text', $admin_username_label, 255);
        $admin_username -> value = 'root';

        $admin_password_label = (string) ($words['databaseAdminPasswordLabel'] ?? '');

        $db_fields = new Fieldset((string) ($words['databaseLegend'] ?? ''));
        $db_fields -> addContent($db_host);
        $db_fields -> addContent($db_port);
        $db_fields -> addContent($db_database);
        $db_fields -> addContent($admin_username);
        $db_fields -> addContent(new InputField('adminPassword', $admin_password_label, 'password', $admin_password_label));
        $this -> contents[] = $db_fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
