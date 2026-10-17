<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Auth;
use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Validation\Validator;

final class UserController extends AdminController
{
    private function find(int $id): array
    {
        return $this->db->one('SELECT u.*, a.id AS author_profile_id, a.display_name AS author_name FROM {{users}} u LEFT JOIN {{authors}} a ON a.user_id = u.id WHERE u.id = ?', [$id])
            ?? throw new HttpException(404, 'User not found.');
    }

    private function activeAdmins(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {{users}} WHERE role = 'admin' AND status = 'active'");
    }

    public function index(): Response
    {
        return $this->page('users/index', [
            'pageTitle' => 'Users & roles',
            'users' => $this->db->all(
                'SELECT u.*, a.display_name AS author_name, (SELECT COUNT(*) FROM {{posts}} p WHERE p.created_by = u.id AND p.deleted_at IS NULL) AS post_count
                 FROM {{users}} u LEFT JOIN {{authors}} a ON a.user_id = u.id ORDER BY u.role = \'admin\' DESC, u.name'
            ),
        ]);
    }

    public function create(): Response
    {
        return $this->page('users/form', ['pageTitle' => 'Add user', 'row' => null]);
    }

    public function edit(string $id): Response
    {
        return $this->page('users/form', ['pageTitle' => 'Edit user', 'row' => $this->find($this->id($id))]);
    }

    private function validate(array $in, ?array $row): array
    {
        $errors = [];
        $v = Validator::make($in, ['name' => 'required|max:120', 'email' => 'required|email|max:190', 'role' => 'required|in:admin,editor,author', 'status' => 'required|in:active,disabled']);
        $errors = $v->errors();
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if (!isset($errors['email']) && $this->db->value('SELECT 1 FROM {{users}} WHERE email = ? AND id <> ?', [$email, (int) ($row['id'] ?? 0)])) {
            $errors['email'] = 'Another user already has this email address.';
        }
        $password = (string) ($in['password'] ?? '');
        if (!$row || $password !== '') {
            $problems = Validator::passwordProblems($password, $this->app->settings->int('security.min_password_length'), [(string) ($in['name'] ?? ''), $email]);
            if ($problems) {
                $errors['password'] = implode(' ', $problems);
            }
        }
        if ($row && $row['role'] === 'admin' && $row['status'] === 'active' && (($in['role'] ?? '') !== 'admin' || ($in['status'] ?? '') !== 'active') && $this->activeAdmins() <= 1) {
            $errors['role'] = 'This is the only active administrator. Promote another user first.';
        }
        if ($row && (int) $row['id'] === $this->app->auth->id() && ($in['status'] ?? '') !== 'active') {
            $errors['status'] = 'You cannot disable your own account.';
        }
        return $errors;
    }

    public function store(): Response
    {
        $in = $this->request->post;
        $errors = $this->validate($in, null);
        if ($errors) {
            return $this->withErrors($this->app->url->admin('users/new'), $errors);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = $this->db->transaction(function ($db) use ($in, $now) {
            $id = $db->insert('users', [
                'name' => trim($in['name']),
                'email' => strtolower(trim($in['email'])),
                'password_hash' => password_hash((string) $in['password'], PASSWORD_DEFAULT),
                'role' => $in['role'],
                'status' => $in['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!empty($in['create_author'])) {
                $authors = $this->app->service(\BlogFlow\Models\Authors::class);
                $db->insert('authors', ['user_id' => $id, 'name' => trim($in['name']), 'display_name' => trim($in['name']), 'slug' => $authors->uniqueSlug(trim($in['name'])), 'created_at' => $now, 'updated_at' => $now]);
            }
            return $id;
        });
        $this->log('user_create', 'user', $id, 'Created user ' . strtolower(trim($in['email'])) . ' (' . $in['role'] . ')');
        $this->flash('success', 'User created. Share the password with them securely; they can change it from their profile.');
        return $this->redirect($this->app->url->admin('users'));
    }

    public function update(string $id): Response
    {
        $row = $this->find($this->id($id));
        $in = $this->request->post;
        $errors = $this->validate($in, $row);
        if ($errors) {
            return $this->withErrors($this->app->url->admin('users/' . $row['id'] . '/edit'), $errors);
        }
        $data = [
            'name' => trim($in['name']),
            'email' => strtolower(trim($in['email'])),
            'role' => $in['role'],
            'status' => $in['status'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        $passwordChanged = (string) ($in['password'] ?? '') !== '';
        if ($passwordChanged) {
            $data['password_hash'] = password_hash((string) $in['password'], PASSWORD_DEFAULT);
        }
        $this->db->update('users', $data, 'id = ?', [$row['id']]);
        if ($passwordChanged || $data['status'] !== 'active' || $data['role'] !== $row['role']) {
            $this->app->auth->bumpSessionVersion((int) $row['id']);
        }
        if (!empty($in['create_author']) && !$row['author_profile_id']) {
            $authors = $this->app->service(\BlogFlow\Models\Authors::class);
            $authors->create(['user_id' => $row['id'], 'name' => $data['name'], 'display_name' => $data['name'], 'slug' => $authors->uniqueSlug($data['name'])]);
        }
        $this->log('user_update', 'user', (int) $row['id'], 'Updated user ' . $data['email'] . ($passwordChanged ? ' (password reset)' : ''));
        $this->flash('success', 'User updated.' . ($passwordChanged ? ' Their other sessions were signed out.' : ''));
        return $this->redirect($this->app->url->admin('users'));
    }

    public function delete(string $id): Response
    {
        $row = $this->find($this->id($id));
        if ((int) $row['id'] === $this->app->auth->id()) {
            $this->flash('error', 'You cannot delete your own account.');
            return $this->redirect($this->app->url->admin('users'));
        }
        if ($row['role'] === 'admin' && $row['status'] === 'active' && $this->activeAdmins() <= 1) {
            $this->flash('error', 'You cannot delete the only active administrator.');
            return $this->redirect($this->app->url->admin('users'));
        }
        $this->db->delete('users', 'id = ?', [$row['id']]);
        $this->log('user_delete', 'user', (int) $row['id'], 'Deleted user ' . $row['email']);
        $this->flash('success', 'User deleted. Their posts and author profile were kept.');
        return $this->redirect($this->app->url->admin('users'));
    }

    public function profile(): Response
    {
        return $this->page('users/profile', ['pageTitle' => 'Your profile', 'row' => $this->find((int) $this->app->auth->id())]);
    }

    public function saveProfile(): Response
    {
        $row = $this->find((int) $this->app->auth->id());
        $in = $this->request->post;
        $errors = Validator::make($in, ['name' => 'required|max:120', 'email' => 'required|email|max:190'])->errors();
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if (!isset($errors['email']) && $this->db->value('SELECT 1 FROM {{users}} WHERE email = ? AND id <> ?', [$email, $row['id']])) {
            $errors['email'] = 'Another user already has this email address.';
        }
        $full = $this->db->one('SELECT password_hash FROM {{users}} WHERE id = ?', [$row['id']]);
        $new = (string) ($in['password'] ?? '');
        $sensitive = $new !== '' || $email !== $row['email'];
        if ($sensitive && !password_verify((string) ($in['current_password'] ?? ''), $full['password_hash'])) {
            $errors['current_password'] = 'Enter your current password to change your email or password.';
        }
        if ($new !== '') {
            $problems = Validator::passwordProblems($new, $this->app->settings->int('security.min_password_length'), [(string) $in['name'], $email]);
            if (!$problems && !hash_equals($new, (string) ($in['password_confirm'] ?? ''))) {
                $problems[] = 'The passwords do not match.';
            }
            if ($problems) {
                $errors['password'] = implode(' ', $problems);
            }
        }
        if ($errors) {
            return $this->withErrors($this->app->url->admin('profile'), $errors);
        }
        $data = ['name' => trim($in['name']), 'email' => $email, 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($new !== '') {
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        $this->db->update('users', $data, 'id = ?', [$row['id']]);
        if ($new !== '') {
            $this->app->auth->bumpSessionVersion((int) $row['id']);
            $this->app->session->regenerate();
        }
        $this->log('profile_update', 'user', (int) $row['id'], $new !== '' ? 'Changed own password' : 'Updated own profile');
        $this->flash('success', $new !== '' ? 'Profile saved. Your password was changed and other sessions were signed out.' : 'Profile saved.');
        return $this->redirect($this->app->url->admin('profile'));
    }

    public function security(): Response
    {
        $attempts = $this->db->all('SELECT ip, success, created_at FROM {{login_attempts}} ORDER BY id DESC LIMIT 50');
        $locked = $this->db->all(
            'SELECT ip, COUNT(*) AS n, MAX(created_at) AS last FROM {{login_attempts}} WHERE success = 0 AND created_at > ? GROUP BY ip HAVING n >= ? ORDER BY n DESC',
            [gmdate('Y-m-d H:i:s', time() - $this->app->settings->int('security.login_lockout_minutes') * 60), $this->app->settings->int('security.login_max_attempts')]
        );
        $checks = [
            ['HTTPS', $this->request->isSecure() || str_starts_with($this->app->url->base(), 'https://'), 'Serve the blog over HTTPS so session cookies are sent securely.'],
            ['Installer locked', is_file(BF_ROOT . '/storage/installed.lock'), 'storage/installed.lock prevents re-running the installer.'],
            ['Debug mode off', !$this->app->debug(), 'Set app.debug to false in the configuration file in production.'],
            ['Config outside web root or protected', getenv('BLOGFLOW_CONFIG') !== false || is_file(BF_ROOT . '/config/.htaccess'), 'The config folder is protected by .htaccess (Apache). On Nginx add the deny rules from DEPLOYMENT.md.'],
            ['Uploads cannot run scripts', is_file(BF_ROOT . '/uploads/.htaccess'), 'uploads/.htaccess disables PHP and only serves images.'],
            ['Config file not world-readable', ((@fileperms(\BlogFlow\Core\Config::path()) ?: 0) & 0004) === 0, 'Set the config file permissions to 640 or 600.'],
        ];
        return $this->page('users/security', [
            'pageTitle' => 'Security',
            'attempts' => $attempts,
            'locked' => $locked,
            'checks' => $checks,
            'roles' => Auth::ROLES,
        ]);
    }
}
