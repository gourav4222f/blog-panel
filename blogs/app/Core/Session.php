<?php

declare(strict_types=1);

namespace BlogFlow\Core;

/**
 * Session wrapper. Sessions are only started for admin and preview requests so
 * that public pages stay cookie-free and cacheable.
 */
final class Session
{
    private bool $started = false;

    public function __construct(
        private string $name,
        private string $cookiePath,
        private bool $secure,
        private string $savePath,
    ) {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', '86400');
        if ($this->savePath !== '' && is_dir($this->savePath) && is_writable($this->savePath)) {
            session_save_path($this->savePath);
        }
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->cookiePath === '' ? '/' : $this->cookiePath . '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('');
        session_start();
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function hasCookie(): bool
    {
        return isset($_COOKIE[$this->name]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie($this->name, '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
        $this->started = false;
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public function pullFlash(): array
    {
        $this->start();
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    /** Stores old form input for re-display after validation errors. */
    public function flashInput(array $input, array $errors = []): void
    {
        $this->start();
        unset($input['_token'], $input['password'], $input['password_confirm'], $input['current_password']);
        $_SESSION['_old'] = $input;
        $_SESSION['_errors'] = $errors;
    }

    public function pullOld(): array
    {
        $this->start();
        $old = ['input' => $_SESSION['_old'] ?? [], 'errors' => $_SESSION['_errors'] ?? []];
        unset($_SESSION['_old'], $_SESSION['_errors']);
        return $old;
    }

    public function csrfToken(): string
    {
        $this->start();
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        $this->start();
        $expected = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }
}
