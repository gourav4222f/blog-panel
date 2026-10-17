<?php

declare(strict_types=1);

namespace BlogFlow\Core;

final class Template
{
    private ?string $layoutName = null;
    private array $layoutData = [];
    private array $open = [];

    public function __construct(private View $view, private string $name, private array $data, private array $sections = [])
    {
    }

    public function render(): string
    {
        $file = $this->view->path($this->name);
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data) {
                extract($__data, EXTR_SKIP);
                include $__file;
            })->call($this, $file, $this->data);
            $content = (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        if ($this->layoutName !== null) {
            $sections = $this->sections;
            $sections['content'] = $content;
            return $this->view->render($this->layoutName, array_merge($this->data, $this->layoutData), $sections);
        }
        return $content;
    }

    public function layout(string $name, array $data = []): void
    {
        $this->layoutName = $name;
        $this->layoutData = $data;
    }

    public function start(string $section): void
    {
        $this->open[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->open);
        $this->sections[$name] = ($this->sections[$name] ?? '') . ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]) && $this->sections[$name] !== '';
    }

    public function insert(string $name, array $data = []): string
    {
        return $this->view->render($name, array_merge($this->data, $data));
    }
}
