<?php

declare(strict_types=1);

namespace BlogFlow\Core;

/**
 * Minimal PHP template renderer with layouts, sections and partials.
 * Templates are plain PHP files under app/Views. Always escape output with e().
 */
final class View
{
    private array $shared = [];

    public function __construct(private string $dir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    public function render(string $name, array $data = [], array $sections = []): string
    {
        return (new Template($this, $name, array_merge($this->shared, $data), $sections))->render();
    }

    public function path(string $name): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#i', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Invalid template name.');
        }
        $file = $this->dir . '/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $name);
        }
        return $file;
    }
}
