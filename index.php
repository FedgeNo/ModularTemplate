<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$page = new Page();

$welcome = new Card();
$welcome -> addContent(new Heading2(Config::get('siteTitle')));
$welcome -> addContent(new Paragraph(SiteInfo::description()));
$page -> addContent($welcome);

$page -> send();
