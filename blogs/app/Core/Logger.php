<?php

declare(strict_types=1);

namespace BlogFlow\Core;

final class Logger
{
    public static function error(\Throwable|string $error, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s%s\n",
            gmdate('Y-m-d H:i:s'),
            $error instanceof \Throwable
                ? get_class($error) . ': ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . "\n" . $error->getTraceAsString()
                : $error,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );
        $dir = BF_ROOT . '/storage/logs';
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . '/error-' . gmdate('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }
}
