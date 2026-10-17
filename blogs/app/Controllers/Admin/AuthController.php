<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Services\Mailer;
use BlogFlow\Validation\Validator;

final class AuthController extends AdminController
{
    public function loginForm(): Response
    {
        if ($this->app->auth->check()) {
            return $this->redirect($this->app->url->admin());
        }
        return $this->page('auth/login', ['installed' => isset($this->request->query['installed'])]);
    }

    public function login(): Response
    {
        $email = $this->request->str('email');
        $password = (string) ($this->request->post['password'] ?? '');
        $result = $this->app->auth->attempt($email, $password, $this->request->ip());
        if (!$result['ok']) {
            $this->app->session->flashInput(['email' => $email]);
            $this->flash('error', $result['error']);
            return $this->redirect($this->app->url->admin('login'));
        }
        $this->log('login', 'user', (int) $result['user']['id'], 'Signed in');
        $intended = (string) $this->app->session->get('intended', '');
        $this->app->session->forget('intended');
        // Only allow returning to admin paths.
        if ($intended !== '' && str_starts_with($intended, '/admin') && !str_contains($intended, '//')) {
            return $this->redirect($this->app->url->basePath() . $intended);
        }
        return $this->redirect($this->app->url->admin());
    }

    public function logout(): Response
    {
        if ($this->app->auth->check()) {
            $this->log('logout', 'user', $this->app->auth->id(), 'Signed out');
        }
        $this->app->auth->logout();
        $this->app->session->destroy();
        $this->app->session->start();
        $this->flash('success', 'You have been signed out.');
        return $this->redirect($this->app->url->admin('login'));
    }

    public function forgotForm(): Response
    {
        return $this->page('auth/forgot');
    }

    public function forgot(): Response
    {
        $email = strtolower($this->request->str('email'));
        // Same response whether or not the account exists.
        $message = 'If an account exists for that address, a reset link has been sent. The link is valid for 60 minutes.';
        $ipCount = (int) $this->db->value('SELECT COUNT(*) FROM {{login_attempts}} WHERE ip = ? AND success = 0 AND created_at > ?', [$this->request->ip(), gmdate('Y-m-d H:i:s', time() - 3600)]);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $ipCount < 20) {
            $user = $this->db->one("SELECT id, name, email FROM {{users}} WHERE email = ? AND status = 'active'", [$email]);
            $recent = $user ? (int) $this->db->value('SELECT COUNT(*) FROM {{password_reset_tokens}} WHERE user_id = ? AND created_at > ?', [$user['id'], gmdate('Y-m-d H:i:s', time() - 900)]) : 0;
            if ($user && $recent < 3) {
                $token = bin2hex(random_bytes(32));
                $this->db->insert('password_reset_tokens', [
                    'user_id' => $user['id'],
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $parts = parse_url($this->app->url->base());
                $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
                $link = $origin . $this->app->url->admin('reset/' . $token);
                $blog = $this->app->settings->str('general.blog_name') ?: 'BlogFlow';
                $this->app->service(Mailer::class)->send($user['email'], 'Reset your ' . $blog . ' password',
                    "Hello {$user['name']},\n\nSomeone requested a password reset for your account. Open this link to choose a new password (valid for 60 minutes):\n\n{$link}\n\nIf you did not request this, you can ignore this email.\n");
            }
            // Count reset requests toward IP throttling.
            $this->db->insert('login_attempts', ['email_hash' => hash('sha256', 'reset:' . $email), 'ip' => $this->request->ip(), 'success' => 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
        $this->flash('success', $message);
        return $this->redirect($this->app->url->admin('login'));
    }

    private function validToken(string $token): ?array
    {
        if (strlen($token) !== 64) {
            return null;
        }
        return $this->db->one(
            'SELECT t.*, u.email, u.name FROM {{password_reset_tokens}} t INNER JOIN {{users}} u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > ? AND u.status = \'active\'',
            [hash('sha256', $token), gmdate('Y-m-d H:i:s')]
        );
    }

    public function resetForm(string $token): Response
    {
        if (!$this->validToken($token)) {
            $this->flash('error', 'This reset link is invalid or has expired. Request a new one.');
            return $this->redirect($this->app->url->admin('forgot'));
        }
        return $this->page('auth/reset', ['token' => $token]);
    }

    public function reset(string $token): Response
    {
        $row = $this->validToken($token);
        if (!$row) {
            $this->flash('error', 'This reset link is invalid or has expired.');
            return $this->redirect($this->app->url->admin('forgot'));
        }
        $password = (string) ($this->request->post['password'] ?? '');
        $problems = Validator::passwordProblems($password, $this->app->settings->int('security.min_password_length'), [$row['name'], $row['email']]);
        if (!$problems && !hash_equals($password, (string) ($this->request->post['password_confirm'] ?? ''))) {
            $problems[] = 'The passwords do not match.';
        }
        if ($problems) {
            return $this->withErrors($this->app->url->admin('reset/' . $token), ['password' => implode(' ', $problems)], []);
        }
        $this->db->transaction(function ($db) use ($row, $password) {
            $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$row['user_id']]);
            $db->query('UPDATE {{users}} SET session_version = session_version + 1 WHERE id = ?', [$row['user_id']]);
            $db->update('password_reset_tokens', ['used_at' => gmdate('Y-m-d H:i:s')], 'user_id = ? AND used_at IS NULL', [$row['user_id']]);
        });
        $this->db->insert('activity_logs', ['user_id' => $row['user_id'], 'action' => 'password_reset', 'entity_type' => 'user', 'entity_id' => $row['user_id'], 'summary' => 'Password reset via email link', 'ip' => $this->request->ip(), 'created_at' => gmdate('Y-m-d H:i:s')]);
        $this->flash('success', 'Your password has been changed. Please sign in.');
        return $this->redirect($this->app->url->admin('login'));
    }
}
