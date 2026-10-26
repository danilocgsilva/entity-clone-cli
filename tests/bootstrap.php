<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Mock environment for testing
putenv('APP_ENV=test');
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME_TEST=test_db');
putenv('DB_USER=test_user');
putenv('DB_PASSWORD=test_pass');
