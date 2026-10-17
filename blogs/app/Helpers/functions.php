<?php

declare(strict_types=1);

use BlogFlow\Core\App;

/** HTML-escape for text and attribute contexts. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function bf_app(): App
{
    return App::get();
}

function bf_setting(string $key, mixed $default = null): mixed
{
    return App::get()->settings->get($key, $default);
}

function bf_url(): \BlogFlow\Core\Url
{
    return App::get()->url;
}

function bf_admin_url(string $path = '', array $query = []): string
{
    return App::get()->url->admin($path, $query);
}

function bf_asset(string $path): string
{
    return App::get()->url->asset($path);
}

function bf_csrf_token(): string
{
    return App::get()->session->csrfToken();
}

function bf_csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(bf_csrf_token()) . '">';
}

function bf_can(string $permission): bool
{
    return App::get()->auth->can($permission);
}

/** Formats a UTC "Y-m-d H:i:s" value in the site timezone. */
function bf_date(?string $utc, ?string $format = null): string
{
    if (!$utc) {
        return '';
    }
    try {
        $tz = new DateTimeZone((string) bf_setting('general.timezone', 'UTC'));
    } catch (Exception) {
        $tz = new DateTimeZone('UTC');
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone($tz)->format($format ?? (string) bf_setting('general.date_format', 'M j, Y'));
}

/** ISO 8601 with offset, in the site timezone (for schema, feeds, time elements). */
function bf_iso(?string $utc): string
{
    return $utc ? bf_date($utc, DATE_ATOM) : '';
}

/** Value for datetime-local inputs in the site timezone. */
function bf_local_input(?string $utc): string
{
    return $utc ? bf_date($utc, 'Y-m-d\TH:i') : '';
}

function bf_ago(?string $utc): string
{
    if (!$utc) {
        return '—';
    }
    $diff = time() - strtotime($utc . ' UTC');
    return match (true) {
        $diff < 60 => 'just now',
        $diff < 3600 => (int) floor($diff / 60) . ' min ago',
        $diff < 86400 => (int) floor($diff / 3600) . ' h ago',
        $diff < 86400 * 7 => (int) floor($diff / 86400) . ' d ago',
        default => bf_date($utc),
    };
}

function bf_bytes(int|float|null $bytes): string
{
    $bytes = (float) ($bytes ?? 0);
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) (int) $bytes : number_format($bytes, $bytes < 10 ? 1 : 0)) . ' ' . $units[$i];
}

/** Old input value after a failed validation redirect. */
function bf_old(array $old, string $key, mixed $default = ''): mixed
{
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function bf_icon(string $name, string $class = 'h-4 w-4'): string
{
    return \BlogFlow\Helpers\Icons::svg($name, $class);
}
