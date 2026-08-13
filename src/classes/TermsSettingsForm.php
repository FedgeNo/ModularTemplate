<?php

declare(strict_types=1);

class TermsSettingsForm extends SiteInfoSettingsForm
{
    protected string $settingName = SiteInfo::TERMS_SETTING;

    protected function currentText(): string
    {
        return SiteInfo::terms();
    }
}
