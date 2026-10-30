<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_checkin_access();
require_once dirname(__DIR__) . '/src/Models/Checkin.php';
require_once dirname(__DIR__) . '/src/Controllers/CheckinController.php';

(new CheckinController())->handle();
