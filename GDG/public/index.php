<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_admin();
require dirname(__DIR__) . '/src/Controllers/AdminDashboardController.php';

(new AdminDashboardController())->index();
