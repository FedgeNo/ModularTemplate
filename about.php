<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the site's overview, and the one reachable place linking out to the
// terms of service and privacy policy.
$page = new Page(['title' => (string) (Strings::for('PageTitle')['about'] ?? '')]);

$about_card = new Card();
$about_card -> addContents(InfoText::paragraphs(SiteInfo::about()));
$page -> addContent($about_card);

$policy_card = new Card();
$policy_card -> addContent(new SitePolicyLinks());
$page -> addContent($policy_card);

$version = new Paragraph('This site runs version ' . APP_VERSION . '.');
$version -> class = 'muted text-sm';

$version_card = new Card();
$version_card -> addContent($version);

$page -> addContent($version_card);

$page -> send();
