<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - visitors deciding whether to sign up need to read this.
$page = new Page(['title' => (string) (Strings::for('PageTitle')['terms'] ?? '')]);

$terms_card = new Card();
$terms_card -> addContents(InfoText::paragraphs(SiteInfo::terms()));
$page -> addContent($terms_card);

$page -> send();
