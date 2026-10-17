<?php

/**
 * BlogFlow CMS front controller. Every public and admin request is routed through here.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
if (defined('BF_ENTRY_SUBDIR')) {
    $basePath = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
}

$request = BlogFlow\Core\Request::fromGlobals($basePath);
$response = (new BlogFlow\Core\Kernel($basePath))->handle($request);
$response->send($request->method === 'HEAD');
