<?php

declare(strict_types=1);

/**
 * The Admin Settings form for outgoing mail: the "from" address/name,
 * plus the SMTP relay. The SMTP password is write-only, never rendered back,
 * and a blank submit leaves it unchanged. Everything else IS shown (nothing
 * sensitive about it). A blank "from" address submit also leaves the stored
 * value unchanged (not write-only for the same reason as the password - it's
 * just that a blank address would break every subsequent email, unlike a
 * blank host, which is a valid state that falls back to PHP's mail()).
 */
class MailSettingsForm extends FormForm
{
    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $from_address = new InputField('mailFromAddress', (string) ($words['fromAddressLabel'] ?? ''), 'email', (string) ($words['fromAddressPlaceholder'] ?? ''), 255);
        $from_address -> value = (string) Settings::get(Mailer::FROM_ADDRESS_SETTING, '');
        $from_address -> autocomplete = 'new-password';
        $from_address -> labelVisible = true;
        $fields -> addContent($from_address);

        $from_name_label = (string) ($words['fromNameLabel'] ?? '');
        $from_name = new InputField('mailFromName', $from_name_label, 'text', $from_name_label, 100);
        $from_name -> value = (string) Settings::get(Mailer::FROM_NAME_SETTING, '');
        $from_name -> autocomplete = 'new-password';
        $from_name -> labelVisible = true;
        $fields -> addContent($from_name);

        $host_label = (string) ($words['hostLabel'] ?? '');
        $host = new InputField('smtpHost', $host_label, 'text', $host_label, 255);
        $host -> value = (string) Settings::get(Mailer::SMTP_HOST_SETTING, '');
        $host -> autocomplete = 'new-password';
        $host -> labelVisible = true;
        $fields -> addContent($host);

        $port_label = (string) ($words['portLabel'] ?? '');
        $port = new InputField('smtpPort', $port_label, 'text', $port_label, 5);
        $port -> value = (string) Settings::get(Mailer::SMTP_PORT_SETTING, '587');
        $port -> autocomplete = 'new-password';
        $port -> labelVisible = true;
        $fields -> addContent($port);

        $username_label = (string) ($words['usernameLabel'] ?? '');
        $username = new InputField('smtpUsername', $username_label, 'text', $username_label, 255);
        $username -> value = (string) Settings::get(Mailer::SMTP_USERNAME_SETTING, '');
        $username -> autocomplete = 'new-password';
        $username -> labelVisible = true;
        $fields -> addContent($username);

        $password_is_set = (string) Settings::get(Mailer::SMTP_PASSWORD_SETTING, '') !== '';
        $password_placeholder = (string) ($password_is_set
            ? ($words['passwordPlaceholder']['set'] ?? '')
            : ($words['passwordPlaceholder']['unset'] ?? ''));
        $password = new InputField('smtpPassword', (string) ($words['passwordLabel'] ?? ''), 'password', $password_placeholder, 255);
        $password -> autocomplete = 'new-password';
        $password -> labelVisible = true;
        $fields -> addContent($password);

        $encryption = Settings::get(Mailer::SMTP_ENCRYPTION_SETTING, 'tls');

        $encryption_label = new Label();
        $encryption_label -> for = 'smtpEncryption';
        $encryption_label -> contents[] = (string) ($words['encryptionLabel'] ?? '');
        $fields -> addContent($encryption_label);

        $encryption_select = new Select();
        $encryption_select -> name = 'smtpEncryption';
        $encryption_select -> id = 'smtpEncryption';

        foreach ((array) ($words['encryptionOptions'] ?? []) as $value => $text) {
            $option = new SelectOption();
            $option -> value = $value;
            $option -> contents[] = (string) $text;

            if ($value === $encryption) {
                $option -> attributes['selected'] = 'selected';
            }

            $encryption_select -> addContent($option);
        }

        $fields -> addContent($encryption_select);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph((string) ($words['explainer'] ?? ''));

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
