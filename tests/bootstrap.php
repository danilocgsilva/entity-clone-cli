<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$testEnv = getenv('APP_ENV');
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbTestDatabase = getenv('DB_NAME_TEST');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');

putenv("APP_ENV={$testEnv}");
putenv("DB_HOST={$dbHost}");
putenv("DB_PORT={$dbPort}");
putenv("DB_NAME_TEST={$dbTestDatabase}");
putenv("DB_USER={$dbUser}");
putenv("DB_PASSWORD={$dbPassword}");
