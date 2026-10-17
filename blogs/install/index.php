<?php

/**
 * BlogFlow CMS installation wizard. Locked automatically after a successful install
 * (storage/installed.lock).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

(new BlogFlow\Install\Installer())->handle();
