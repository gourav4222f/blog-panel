<?php

declare(strict_types=1);

namespace BlogFlow\Core;

final class Response
{
    private array $headers = [];

    public function __construct(private string $body = '', private int $status = 200, array $headers = [])
    {
        foreach ($headers as $k => $v) {
            $this->header($k, $v);
        }
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return new self((string) $body, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        // Strip CR/LF to prevent header injection.
        $url = str_replace(["\r", "\n", "\0"], '', $url);
        return new self('', $status, ['Location' => $url]);
    }

    public static function xml(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = str_replace(["\r", "\n"], '', $value);
        return $this;
    }

    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function send(bool $headOnly = false): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v, true);
            }
        }
        if (!$headOnly) {
            echo $this->body;
        }
    }
}
