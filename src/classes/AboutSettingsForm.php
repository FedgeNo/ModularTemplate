<?php

declare(strict_types=1);

class AboutSettingsForm extends SiteInfoSettingsForm
{
    protected string $settingName = SiteInfo::ABOUT_SETTING;

    protected function currentText(): string
    {
        return SiteInfo::about();
    }
}
