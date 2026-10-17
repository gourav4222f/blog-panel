<?php

declare(strict_types=1);

namespace BlogFlow\Core;

use BlogFlow\Services\Settings;

/**
 * Per-request service container.
 */
final class App
{
    private static ?self $instance = null;
    private array $services = [];

    public function __construct(
        public readonly array $config,
        public readonly Database $db,
        public readonly Cache $cache,
        public readonly Settings $settings,
        public readonly Session $session,
        public readonly Auth $auth,
        public readonly View $view,
        public readonly Url $url,
        public readonly Request $request,
    ) {
    }

    public static function set(self $app): void
    {
        self::$instance = $app;
    }

    public static function get(): self
    {
        return self::$instance ?? throw new \RuntimeException('Application not booted.');
    }

    public static function booted(): bool
    {
        return self::$instance !== null;
    }

    /** Lazily constructs and memoises a service class that takes App as its only argument. */
    public function service(string $class): object
    {
        return $this->services[$class] ??= new $class($this);
    }

    public function debug(): bool
    {
        return !empty($this->config['app']['debug']);
    }
}
