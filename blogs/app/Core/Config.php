<?php

declare(strict_types=1);

namespace BlogFlow\Core;

/**
 * Locates and loads the private configuration file.
 *
 * Lookup order:
 *   1. BLOGFLOW_CONFIG environment variable (recommended: a path outside the web root)
 *   2. config/config.php inside the application (protected by .htaccess)
 */
final class Config
{
    public static function path(): string
    {
        $env = getenv('BLOGFLOW_CONFIG');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return BF_ROOT . '/config/config.php';
    }

    public static function load(): ?array
    {
        $file = self::path();
        if (!is_file($file)) {
            return null;
        }
        $config = require $file;
        return is_array($config) && isset($config['db'], $config['app']['key']) ? $config : null;
    }

    public static function installed(): bool
    {
        return is_file(BF_ROOT . '/storage/installed.lock') && self::load() !== null;
    }

    public static function write(string $file, array $config): bool
    {
        $php = "<?php\n// BlogFlow CMS private configuration. Never commit or share this file.\n"
            . "defined('BLOGFLOW') || exit;\n\nreturn " . var_export($config, true) . ";\n";
        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        $ok = Cache::writeAtomic($file, $php);
        if ($ok) {
            @chmod($file, 0640);
        }
        return $ok;
    }
}
