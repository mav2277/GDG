<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_admin();
require_once dirname(__DIR__) . '/src/Controllers/CertificateController.php';

(new CertificateController())->handle();
