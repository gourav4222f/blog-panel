<?php

declare(strict_types=1);

namespace BlogFlow\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly array $headers = [])
    {
        parent::__construct($message !== '' ? $message : self::reason($status), $status);
    }

    public static function reason(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            403 => 'Forbidden',
            404 => 'Page not found',
            405 => 'Method not allowed',
            419 => 'Your session expired. Please reload the page and try again.',
            422 => 'The submitted data is invalid.',
            429 => 'Too many requests',
            503 => 'Service unavailable',
            default => 'Server error',
        };
    }
}
