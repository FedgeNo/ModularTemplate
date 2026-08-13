<?php

declare(strict_types=1);

require_once __DIR__ . '/src/init.php';

ErrorDocument::send(404, 'Not Found', 'The page you\'re looking for doesn\'t exist.');
