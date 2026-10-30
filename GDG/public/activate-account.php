<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Controllers/InvitationController.php';

$controller = new InvitationController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->accept();
} else {
    $controller->show();
}
