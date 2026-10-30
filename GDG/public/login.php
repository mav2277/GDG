<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if (!defined('CLEAN_AUTH_ROUTE')) {
    define('CLEAN_AUTH_ROUTE', false);
}

$auth = new AuthController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->login();
} else {
    $auth->showLogin();
}
