<?php

declare(strict_types=1);

$environment = dirname(__DIR__).'/.env';

if (! is_file($environment)) {
    file_put_contents($environment, "APP_ENV=testing\n");
}

require dirname(__DIR__).'/vendor/autoload.php';
