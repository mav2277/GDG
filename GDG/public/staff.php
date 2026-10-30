<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_staff();
require_once dirname(__DIR__) . '/src/Controllers/StaffController.php';

(new StaffController())->index();
