<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('BlogFlow CMS requires PHP 8.2 or newer. This server runs PHP ' . PHP_VERSION . '.');
}

define('BLOGFLOW', true);
define('BF_ROOT', dirname(__DIR__));
define('BF_VERSION', '1.0.0');

if (!is_file(BF_ROOT . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('BlogFlow CMS: the vendor/ directory is missing. Upload the complete release package.');
}
require BF_ROOT . '/vendor/autoload.php';
require BF_ROOT . '/app/Helpers/functions.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
        // Logged by PHP; never fatal for visitors.
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
