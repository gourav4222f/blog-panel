<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\Cache;
use BlogFlow\Core\Database;

/**
 * Key/value settings backed by the settings table, with schema defaults.
 * Values are JSON encoded. All rows are loaded once per request (and cached on disk).
 */
final class Settings
{
    private ?array $values = null;

    public function __construct(private Database $db, private Cache $cache)
    {
    }

    private function load(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }
        $rows = $this->cache->get('settings');
        if (!is_array($rows)) {
            $rows = [];
            foreach ($this->db->all('SELECT name, value FROM {{settings}}') as $row) {
                $rows[$row['name']] = json_decode((string) $row['value'], true);
            }
            $this->cache->set('settings', $rows);
        }
        return $this->values = $rows;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->load();
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        $field = SettingsSchema::field($key);
        if ($field !== null) {
            return $field['default'] ?? $default;
        }
        return $default;
    }

    public function str(string $key): string
    {
        $v = $this->get($key, '');
        return is_scalar($v) ? (string) $v : '';
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    /** @return array<string, mixed> key => value for a group, with defaults applied. */
    public function group(string $group): array
    {
        $out = [];
        foreach (SettingsSchema::group($group)['fields'] as $key => $field) {
            $out[$key] = $this->get($group . '.' . $key);
        }
        return $out;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    public function setMany(array $values): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transaction(function (Database $db) use ($values, $now) {
            foreach ($values as $key => $value) {
                $db->query(
                    'INSERT INTO {{settings}} (name, value, updated_at) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
                    [$key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now]
                );
            }
        });
        $this->cache->forget('settings');
        $this->values = null;
    }

    public function forget(string $key): void
    {
        $this->db->delete('settings', 'name = ?', [$key]);
        $this->cache->forget('settings');
        $this->values = null;
    }

    /**
     * Validates submitted values for a group against the schema.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [clean values, errors]
     */
    public static function validateGroup(string $group, array $input, array $current, bool $allowCode = true): array
    {
        $clean = [];
        $errors = [];
        foreach (SettingsSchema::group($group)['fields'] as $key => $f) {
            $type = $f['type'];
            if (!array_key_exists($key, $input)) {
                // Fields not submitted keep their current value (partial updates, imports).
                $clean[$key] = $current[$key] ?? ($f['default'] ?? null);
                continue;
            }
            $raw = $input[$key];
            $label = $f['label'];
            switch ($type) {
                case 'bool':
                    $clean[$key] = in_array($raw, ['1', 1, 'on', true, 'true'], true);
                    break;
                case 'int':
                    if ($raw === null || $raw === '') {
                        $clean[$key] = $f['default'];
                        break;
                    }
                    if (!is_numeric($raw)) {
                        $errors[$key] = $label . ' must be a number.';
                        break;
                    }
                    $n = (int) $raw;
                    if ((isset($f['min']) && $n < $f['min']) || (isset($f['max']) && $n > $f['max'])) {
                        $errors[$key] = sprintf('%s must be between %d and %d.', $label, $f['min'] ?? PHP_INT_MIN, $f['max'] ?? PHP_INT_MAX);
                        break;
                    }
                    $clean[$key] = $n;
                    break;
                case 'select':
                case 'timezone':
                    $value = is_scalar($raw) ? (string) $raw : '';
                    $options = $type === 'timezone' ? array_flip(\DateTimeZone::listIdentifiers()) : $f['options'];
                    if (!array_key_exists($value, $options)) {
                        $errors[$key] = 'Choose a valid option for ' . $label . '.';
                        break;
                    }
                    $clean[$key] = $value;
                    break;
                case 'multicheck':
                    $values = is_array($raw) ? array_values(array_intersect(array_map('strval', $raw), array_keys($f['options']))) : [];
                    $clean[$key] = $values;
                    break;
                case 'color':
                    $value = is_scalar($raw) ? strtoupper(trim((string) $raw)) : '';
                    if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
                        $errors[$key] = $label . ' must be a hex colour like #1A2B3C.';
                        break;
                    }
                    $clean[$key] = $value;
                    break;
                case 'media':
                    $clean[$key] = is_numeric($raw) && (int) $raw > 0 ? (int) $raw : null;
                    break;
                case 'password':
                    // Empty submission keeps the stored secret.
                    $value = is_scalar($raw) ? (string) $raw : '';
                    $clean[$key] = $value === '' ? ($current[$key] ?? '') : $value;
                    break;
                case 'code':
                    if (!$allowCode) {
                        $clean[$key] = $current[$key] ?? '';
                        break;
                    }
                    $clean[$key] = is_scalar($raw) ? str_replace("\0", '', (string) $raw) : '';
                    if (strlen($clean[$key]) > 20000) {
                        $errors[$key] = $label . ' is too long.';
                    }
                    break;
                default: // text, textarea, url, email
                    $value = is_scalar($raw) ? trim(str_replace("\0", '', (string) $raw)) : '';
                    if ($type !== 'textarea') {
                        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
                    }
                    if ($value === '') {
                        if (!empty($f['required'])) {
                            $errors[$key] = $label . ' is required.';
                        }
                        $clean[$key] = '';
                        break;
                    }
                    if (isset($f['max']) && mb_strlen($value) > $f['max']) {
                        $errors[$key] = sprintf('%s must be at most %d characters.', $label, $f['max']);
                        break;
                    }
                    if ($type === 'url') {
                        if (!\BlogFlow\Validation\Validator::isHttpUrl($value) || (!empty($f['https']) && !str_starts_with(strtolower($value), 'https://'))) {
                            $errors[$key] = $label . (!empty($f['https']) ? ' must be a valid https:// URL.' : ' must be a valid http(s) URL.');
                            break;
                        }
                    }
                    if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$key] = $label . ' must be a valid email address.';
                        break;
                    }
                    if (isset($f['pattern']) && !preg_match($f['pattern'], $value)) {
                        $errors[$key] = $label . ' has an invalid format.';
                        break;
                    }
                    $clean[$key] = $value;
            }
        }
        return [$clean, $errors];
    }
}
