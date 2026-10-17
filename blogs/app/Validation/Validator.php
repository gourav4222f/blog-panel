<?php

declare(strict_types=1);

namespace BlogFlow\Validation;

/**
 * Server-side validation with a compact rule syntax:
 *   Validator::make($data, ['title' => 'required|max:255', 'email' => 'required|email'])
 * Supported rules: required, max:n, min:n, email, url, slug, int, between:a,b, in:a,b,c, color, date.
 */
final class Validator
{
    private array $errors = [];

    private function __construct(private array $data, array $rules, array $labels)
    {
        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $value = $data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $rulesList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $empty = $value === null || $value === '' || $value === [];
            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'required') {
                    if ($empty) {
                        $this->errors[$field] = $label . ' is required.';
                        break;
                    }
                    continue;
                }
                if ($empty) {
                    continue;
                }
                $error = $this->check($name, $arg, $value, $label);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
        }
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    private function check(string $name, ?string $arg, mixed $value, string $label): ?string
    {
        $str = is_scalar($value) ? (string) $value : '';
        return match ($name) {
            'max' => mb_strlen($str) > (int) $arg ? sprintf('%s must be at most %d characters.', $label, (int) $arg) : null,
            'min' => mb_strlen($str) < (int) $arg ? sprintf('%s must be at least %d characters.', $label, (int) $arg) : null,
            'email' => filter_var($str, FILTER_VALIDATE_EMAIL) ? null : $label . ' must be a valid email address.',
            'url' => self::isHttpUrl($str) ? null : $label . ' must be a valid http(s) URL.',
            'slug' => self::isSlug($str) ? null : $label . ' may only contain lowercase letters, numbers and hyphens.',
            'int' => filter_var($str, FILTER_VALIDATE_INT) !== false ? null : $label . ' must be a whole number.',
            'between' => (function () use ($str, $arg, $label) {
                [$a, $b] = array_map('intval', explode(',', (string) $arg));
                $n = filter_var($str, FILTER_VALIDATE_INT);
                return $n === false || $n < $a || $n > $b ? sprintf('%s must be between %d and %d.', $label, $a, $b) : null;
            })(),
            'in' => in_array($str, explode(',', (string) $arg), true) ? null : 'Choose a valid ' . strtolower($label) . '.',
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $str) ? null : $label . ' must be a hex colour.',
            'date' => self::parseDate($str) ? null : $label . ' must be a valid date and time.',
            default => throw new \InvalidArgumentException('Unknown validation rule ' . $name),
        };
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public static function isHttpUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\s<>"\x00-\x1f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && !empty($parts['host']);
    }

    public static function isSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) && strlen($slug) <= 190;
    }

    public static function slugify(string $text, int $max = 120): string
    {
        $text = trim(str_replace(["'", '’', '‘', '`'], '', $text));
        if ($text === '') {
            return '';
        }
        if (class_exists(\Transliterator::class)) {
            $tr = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($tr) {
                $text = (string) $tr->transliterate($text);
            }
        } elseif (function_exists('iconv')) {
            $text = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        }
        $text = strtolower($text);
        $text = str_replace(['&', '@', '%'], [' and ', ' at ', ' percent '], $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        if (strlen($text) > $max) {
            $text = rtrim(substr($text, 0, $max), '-');
            $cut = strrpos($text, '-');
            if ($cut !== false && $cut > $max * 0.6) {
                $text = substr($text, 0, $cut);
            }
        }
        return $text;
    }

    /** Parses "Y-m-d\TH:i" (datetime-local) or "Y-m-d H:i:s" in the given timezone. */
    public static function parseDate(string $value, string $tz = 'UTC'): ?\DateTimeImmutable
    {
        $value = trim($value);
        try {
            $zone = new \DateTimeZone($tz);
        } catch (\Exception) {
            $zone = new \DateTimeZone('UTC');
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
            if ($d !== false && $d->format($format) === $value) {
                return $d;
            }
        }
        return null;
    }

    public static function passwordProblems(string $password, int $minLength, array $context = []): array
    {
        $problems = [];
        if (mb_strlen($password) < $minLength) {
            $problems[] = sprintf('Password must be at least %d characters.', $minLength);
        }
        if (strlen($password) > 4096) {
            $problems[] = 'Password is too long.';
        }
        if (preg_match('/^(.)\1+$/', $password)) {
            $problems[] = 'Password cannot be a single repeated character.';
        }
        $common = ['password', 'password1', 'password123', '12345678', '123456789', '1234567890', 'qwertyuiop', 'administrator', 'letmein123', 'welcome123', 'admin12345', 'iloveyou12'];
        if (in_array(strtolower($password), $common, true)) {
            $problems[] = 'That password is too common.';
        }
        foreach ($context as $c) {
            if (is_string($c) && $c !== '' && mb_strlen($c) >= 4 && stripos($password, $c) !== false && mb_strlen($password) < mb_strlen($c) + 6) {
                $problems[] = 'Password is too similar to your name or email.';
                break;
            }
        }
        return $problems;
    }
}
