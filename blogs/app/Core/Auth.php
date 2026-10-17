<?php

declare(strict_types=1);

namespace BlogFlow\Core;

use BlogFlow\Services\Settings;

/**
 * Session authentication, login throttling and role-based permissions.
 */
final class Auth
{
    public const ROLES = [
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'author' => 'Author',
    ];

    private const PERMISSIONS = [
        'editor' => [
            'dashboard', 'posts.view', 'posts.create', 'posts.edit_all', 'posts.publish', 'posts.delete_all',
            'taxonomy.manage', 'authors.manage', 'media.view', 'media.upload', 'media.manage',
            'seo.view', 'seo.manage', 'redirects.manage', 'activity.view',
        ],
        'author' => [
            'dashboard', 'posts.view', 'posts.create', 'posts.edit_own', 'posts.delete_own', 'media.view', 'seo.view',
        ],
    ];

    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(private Database $db, private Session $session, private Settings $settings)
    {
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        if (!$this->session->hasCookie() && !$this->session->isStarted()) {
            return null;
        }
        $id = (int) $this->session->get('uid', 0);
        if ($id <= 0) {
            return null;
        }
        $now = time();
        $idle = max(10, $this->settings->int('security.session_idle_minutes')) * 60;
        $max = max(1, $this->settings->int('security.session_max_hours')) * 3600;
        $last = (int) $this->session->get('last_seen', 0);
        $loginAt = (int) $this->session->get('login_at', 0);
        if ($now - $last > $idle || $now - $loginAt > $max) {
            $this->clearSession();
            $this->session->flash('info', 'Your session expired. Please sign in again.');
            return null;
        }
        $user = $this->db->one('SELECT u.*, a.id AS author_profile_id FROM {{users}} u LEFT JOIN {{authors}} a ON a.user_id = u.id WHERE u.id = ?', [$id]);
        if (!$user || $user['status'] !== 'active' || (int) $user['session_version'] !== (int) $this->session->get('sv', 0)) {
            $this->clearSession();
            return null;
        }
        // Light binding to the browser; a changed user agent invalidates the session.
        if ($this->session->get('ua') !== hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            $this->clearSession();
            return null;
        }
        if ($now - $last > 60) {
            $this->session->set('last_seen', $now);
        }
        unset($user['password_hash']);
        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user() ? (int) $this->user()['id'] : null;
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        return self::roleCan($user['role'], $permission, $this->settings);
    }

    public static function roleCan(string $role, string $permission, Settings $settings): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'author') {
            if ($permission === 'posts.publish_own') {
                return $settings->bool('security.authors_can_publish');
            }
            if ($permission === 'media.upload') {
                return $settings->bool('security.authors_can_upload');
            }
        }
        if ($role === 'editor' && $permission === 'posts.publish_own') {
            return true;
        }
        return in_array($permission, self::PERMISSIONS[$role] ?? [], true);
    }

    /** Whether the current user may edit the given post row. */
    public function canEditPost(array $post): bool
    {
        if ($this->can('posts.edit_all')) {
            return true;
        }
        return $this->can('posts.edit_own') && $this->ownsPost($post);
    }

    public function canPublishPost(array $post): bool
    {
        if ($this->can('posts.publish')) {
            return true;
        }
        return $this->ownsPost($post) && $this->can('posts.publish_own');
    }

    public function ownsPost(array $post): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        return (int) ($post['created_by'] ?? 0) === (int) $user['id']
            || ($user['author_profile_id'] !== null && (int) ($post['author_id'] ?? 0) === (int) $user['author_profile_id']);
    }

    /**
     * @return array{ok: bool, error?: string, user?: array}
     */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        $emailHash = hash('sha256', $email);
        $max = max(3, $this->settings->int('security.login_max_attempts'));
        $window = max(5, $this->settings->int('security.login_lockout_minutes'));
        $since = gmdate('Y-m-d H:i:s', time() - $window * 60);

        $byEmail = (int) $this->db->value('SELECT COUNT(*) FROM {{login_attempts}} WHERE email_hash = ? AND success = 0 AND created_at > ?', [$emailHash, $since]);
        $byIp = (int) $this->db->value('SELECT COUNT(*) FROM {{login_attempts}} WHERE ip = ? AND success = 0 AND created_at > ?', [$ip, $since]);
        if ($byEmail >= $max || $byIp >= $max * 4) {
            return ['ok' => false, 'error' => sprintf('Too many failed sign-in attempts. Please wait %d minutes and try again.', $window)];
        }

        $user = $this->db->one('SELECT * FROM {{users}} WHERE email = ?', [$email]);
        // Always run a hash verification to keep response timing uniform.
        $hash = $user['password_hash'] ?? '$2y$10$f9TbYULV7gMcfpsPhaWWbuK3OtJSSiw5q1/aRL44Aw1LJCFbm7G5G';
        $valid = password_verify($password, $hash);

        if (!$user || !$valid || $user['status'] !== 'active') {
            $this->db->insert('login_attempts', ['email_hash' => $emailHash, 'ip' => $ip, 'success' => 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
            return ['ok' => false, 'error' => 'The email or password is incorrect.'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        $this->db->insert('login_attempts', ['email_hash' => $emailHash, 'ip' => $ip, 'success' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $this->db->update('users', ['last_login_at' => gmdate('Y-m-d H:i:s'), 'last_login_ip' => $ip], 'id = ?', [$user['id']]);
        // Clear old attempts opportunistically.
        $this->db->delete('login_attempts', 'created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400 * 30)]);

        $this->login($user);
        return ['ok' => true, 'user' => $user];
    }

    public function login(array $user): void
    {
        $this->session->regenerate();
        $csrf = bin2hex(random_bytes(32));
        $_SESSION = [
            'uid' => (int) $user['id'],
            'sv' => (int) $user['session_version'],
            'login_at' => time(),
            'last_seen' => time(),
            'ua' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            '_csrf' => $csrf,
        ];
        $this->resolved = false;
        $this->user = null;
    }

    public function logout(): void
    {
        $this->clearSession();
    }

    private function clearSession(): void
    {
        $this->session->start();
        $flash = $_SESSION['_flash'] ?? [];
        $_SESSION = [];
        $this->session->regenerate();
        if ($flash) {
            $_SESSION['_flash'] = $flash;
        }
        $this->user = null;
        $this->resolved = true;
    }

    /** Invalidates every session of a user (e.g. after a password change). */
    public function bumpSessionVersion(int $userId): void
    {
        $this->db->query('UPDATE {{users}} SET session_version = session_version + 1 WHERE id = ?', [$userId]);
        if ($this->id() === $userId) {
            $this->session->set('sv', (int) $this->db->value('SELECT session_version FROM {{users}} WHERE id = ?', [$userId]));
        }
    }
}
