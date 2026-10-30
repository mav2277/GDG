<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Utils/session.php';
require_once __DIR__ . '/Utils/security.php';
require_once __DIR__ . '/Utils/auth.php';
require_once __DIR__ . '/Utils/time.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Controllers/AuthController.php';

start_app_session();
