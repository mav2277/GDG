<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (!defined('CLEAN_AUTH_ROUTE')) {
    define('CLEAN_AUTH_ROUTE', isset($_GET['clean_route']) && $_GET['clean_route'] === '1');
}

$auth = new AuthController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->createInitialAdmin();
} else {
    $auth->showInitialAdminSetup();
}
