<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

Auth::logout();

JSONResponse::success(['loggedOut' => true]) -> send();
