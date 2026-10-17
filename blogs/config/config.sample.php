<?php
// BlogFlow CMS configuration — SAMPLE ONLY. The installer creates config/config.php for you.
// Never commit or share a real config file. Keep it unreadable from the web (see DEPLOYMENT.md),
// or move it outside the web root and point the BLOGFLOW_CONFIG environment variable at it.
defined('BLOGFLOW') || exit;

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'database_name',
        'user' => 'database_user',
        'pass' => 'database_password',
        'prefix' => 'bf_',
        // 'socket' => '/var/run/mysqld/mysqld.sock', // optional, instead of host/port
    ],
    'app' => [
        // 32 random bytes, base64 encoded. Generate with: php -r "echo base64_encode(random_bytes(32));"
        'key' => 'REPLACE_WITH_A_RANDOM_KEY',
        // Show exception details on error pages. Never enable on a live site.
        'debug' => false,
        // Optional hard override for the canonical blog URL (otherwise Settings → URL settings is used).
        'url' => '',
    ],
];
