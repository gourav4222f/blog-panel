<?php

declare(strict_types=1);

namespace BlogFlow\Install;

use BlogFlow\Core\Cache;
use BlogFlow\Core\Config;
use BlogFlow\Core\Database;
use BlogFlow\Core\View;
use BlogFlow\Services\ImageService;
use BlogFlow\Services\ThemeService;
use BlogFlow\Validation\Validator;

/**
 * Web installation wizard. Refuses to run once storage/installed.lock exists.
 */
final class Installer
{
    private View $view;
    private string $basePath;

    public function __construct()
    {
        $this->view = new View(BF_ROOT . '/app/Views');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
        $this->basePath = rtrim(dirname($script, 2), '/');
    }

    public static function lockFile(): string
    {
        return BF_ROOT . '/storage/installed.lock';
    }

    public function handle(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'");

        if (is_file(self::lockFile())) {
            http_response_code(403);
            echo $this->render('install/locked', ['adminUrl' => $this->basePath . '/admin/']);
            return;
        }

        session_name('bf_install');
        session_set_cookie_params(['path' => $this->basePath . '/install/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => $this->isSecure()]);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $_SESSION['data'] ??= [];

        $step = (string) ($_GET['step'] ?? 'requirements');
        $errors = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!hash_equals($_SESSION['csrf'], (string) ($_POST['_token'] ?? ''))) {
                $errors['_form'] = 'Your session expired. Please submit the form again.';
            } else {
                [$next, $errors] = match ($step) {
                    'database' => $this->saveDatabase($_POST),
                    'site' => $this->saveSite($_POST),
                    'admin' => $this->saveAdmin($_POST),
                    'finish' => $this->finish(),
                    default => ['requirements', []],
                };
                if (!$errors) {
                    $this->redirect($next);
                    return;
                }
            }
        }

        $checks = $this->requirements();
        $requirementsOk = !in_array(false, array_column(array_filter($checks, fn ($c) => $c['required']), 'ok'), true);
        $order = ['requirements', 'database', 'site', 'admin', 'finish'];
        if (!in_array($step, $order, true)) {
            $step = 'requirements';
        }
        // Do not allow skipping ahead.
        $need = ['database' => $requirementsOk, 'site' => isset($_SESSION['data']['db']), 'admin' => isset($_SESSION['data']['site']), 'finish' => isset($_SESSION['data']['admin'])];
        if (isset($need[$step]) && !$need[$step]) {
            $step = 'requirements';
        }

        echo $this->render('install/' . $step, [
            'step' => $step,
            'steps' => ['requirements' => 'Environment', 'database' => 'Database', 'site' => 'Site', 'admin' => 'Administrator', 'finish' => 'Install'],
            'checks' => $checks,
            'requirementsOk' => $requirementsOk,
            'errors' => $errors,
            'data' => $_SESSION['data'],
            'old' => $_POST,
            'token' => $_SESSION['csrf'],
            'defaults' => $this->defaults(),
            'basePath' => $this->basePath,
        ]);
    }

    private function render(string $template, array $data): string
    {
        return $this->view->render($template, $data + ['assetBase' => $this->basePath . '/assets']);
    }

    private function redirect(string $step): void
    {
        header('Location: ' . $this->basePath . '/install/?step=' . $step, true, 303);
    }

    private function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private function defaults(): array
    {
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $scheme = $this->isSecure() ? 'https' : 'http';
        $tz = date_default_timezone_get();
        return [
            'blog_url' => $scheme . '://' . $host . $this->basePath,
            'main_site_url' => $scheme . '://' . $host . '/',
            'timezone' => in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'UTC',
        ];
    }

    public function requirements(): array
    {
        $caps = ImageService::capabilities();
        $writable = fn (string $d) => is_dir(BF_ROOT . '/' . $d) && is_writable(BF_ROOT . '/' . $d);
        $configTarget = Config::path();
        $checks = [
            ['label' => 'PHP 8.2 or newer', 'ok' => PHP_VERSION_ID >= 80200, 'detail' => 'Running PHP ' . PHP_VERSION, 'required' => true],
            ['label' => 'PDO MySQL extension', 'ok' => extension_loaded('pdo_mysql'), 'detail' => 'Needed for the database connection', 'required' => true],
            ['label' => 'mbstring extension', 'ok' => extension_loaded('mbstring'), 'detail' => 'Unicode text handling', 'required' => true],
            ['label' => 'DOM & libxml extensions', 'ok' => extension_loaded('dom') && extension_loaded('libxml'), 'detail' => 'HTML sanitising and rendering', 'required' => true],
            ['label' => 'fileinfo extension', 'ok' => extension_loaded('fileinfo'), 'detail' => 'Upload type validation', 'required' => true],
            ['label' => 'JSON extension', 'ok' => function_exists('json_encode'), 'detail' => '', 'required' => true],
            ['label' => 'Image library (GD or Imagick)', 'ok' => $caps['driver'] !== 'none', 'detail' => $caps['driver'] === 'none' ? 'Install the GD extension' : 'Using ' . strtoupper($caps['driver']), 'required' => true],
            ['label' => 'WebP output', 'ok' => ImageService::canWrite('webp'), 'detail' => ImageService::canWrite('webp') ? 'Supported' : 'Not available — images are served as JPEG/PNG only', 'required' => false],
            ['label' => 'AVIF output', 'ok' => ImageService::canWrite('avif'), 'detail' => ImageService::canWrite('avif') ? 'Supported' : 'Not available on this server (optional)', 'required' => false],
            ['label' => 'EXIF extension', 'ok' => $caps['exif'], 'detail' => 'Corrects photo orientation from cameras/phones', 'required' => false],
            ['label' => 'intl extension', 'ok' => class_exists(\Transliterator::class), 'detail' => 'Better URL slugs for non-English titles', 'required' => false],
            ['label' => 'zip extension', 'ok' => class_exists(\ZipArchive::class), 'detail' => 'Media backup archives', 'required' => false],
            ['label' => 'Config directory writable', 'ok' => is_writable(dirname($configTarget)), 'detail' => dirname($configTarget), 'required' => true],
        ];
        foreach (['storage', 'storage/cache', 'storage/logs', 'storage/backups', 'storage/originals', 'storage/tmp', 'storage/sessions', 'uploads'] as $d) {
            $checks[] = ['label' => $d . '/ writable', 'ok' => $writable($d), 'detail' => 'Set permissions to 755 (or 775) for this folder', 'required' => true];
        }
        return $checks;
    }

    private function saveDatabase(array $in): array
    {
        $db = [
            'host' => trim((string) ($in['host'] ?? 'localhost')) ?: 'localhost',
            'port' => (int) ($in['port'] ?? 3306) ?: 3306,
            'name' => trim((string) ($in['name'] ?? '')),
            'user' => trim((string) ($in['user'] ?? '')),
            'pass' => (string) ($in['pass'] ?? ''),
            'prefix' => trim((string) ($in['prefix'] ?? 'bf_')),
        ];
        $errors = [];
        if ($db['name'] === '') {
            $errors['name'] = 'Enter the database name.';
        }
        if ($db['user'] === '') {
            $errors['user'] = 'Enter the database username.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{0,20}$/', $db['prefix'])) {
            $errors['prefix'] = 'The prefix may contain letters, numbers and underscores (max 20).';
        }
        if (!preg_match('/^[A-Za-z0-9.\-_:\[\]\/]+$/', $db['host'])) {
            $errors['host'] = 'Enter a valid host name.';
        }
        if ($errors) {
            return ['database', $errors];
        }
        try {
            $pdo = Database::connect($db);
            $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $isMaria = stripos($version, 'mariadb') !== false;
            $num = preg_match('/(\d+\.\d+\.\d+)/', $version, $m) ? $m[1] : '0';
            if ((!$isMaria && version_compare($num, '5.7.7', '<')) || ($isMaria && version_compare($num, '10.3.0', '<'))) {
                return ['database', ['_form' => 'MySQL 5.7.7+ (8.0 recommended) or MariaDB 10.3+ is required. Server reports ' . $version . '.']];
            }
            $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote(addcslashes($db['prefix'], '_%') . 'posts'))->fetchColumn();
            if ($exists) {
                return ['database', ['prefix' => 'BlogFlow tables with this prefix already exist in the database. Use a different prefix or an empty database.']];
            }
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $friendly = match (true) {
                str_contains($msg, '1045') => 'Access denied: check the username and password.',
                str_contains($msg, '1049') => 'The database does not exist. Create it in your hosting control panel first.',
                str_contains($msg, '2002') || str_contains($msg, '2005') => 'Could not reach the database server. Check the host and port.',
                default => 'Could not connect to the database.',
            };
            return ['database', ['_form' => $friendly]];
        }
        $_SESSION['data']['db'] = $db;
        return ['site', []];
    }

    private function saveSite(array $in): array
    {
        $site = [
            'blog_url' => rtrim(trim((string) ($in['blog_url'] ?? '')), '/'),
            'main_site_url' => trim((string) ($in['main_site_url'] ?? '')),
            'main_site_name' => trim((string) ($in['main_site_name'] ?? '')),
            'blog_name' => trim((string) ($in['blog_name'] ?? '')),
            'timezone' => (string) ($in['timezone'] ?? 'UTC'),
            'language' => trim((string) ($in['language'] ?? 'en')),
            'preset' => (string) ($in['preset'] ?? 'minimal'),
        ];
        $errors = [];
        if (!Validator::isHttpUrl($site['blog_url'])) {
            $errors['blog_url'] = 'Enter the full blog URL, e.g. https://example.com/blogs';
        } elseif (rtrim((string) parse_url($site['blog_url'], PHP_URL_PATH), '/') !== $this->basePath) {
            $errors['blog_url'] = sprintf('The URL path must match the folder the blog is installed in (%s).', $this->basePath === '' ? '/' : $this->basePath);
        }
        if ($site['main_site_url'] !== '' && !Validator::isHttpUrl($site['main_site_url'])) {
            $errors['main_site_url'] = 'Enter a valid URL or leave empty.';
        }
        if ($site['blog_name'] === '' || mb_strlen($site['blog_name']) > 120) {
            $errors['blog_name'] = 'Enter a blog name (max 120 characters).';
        }
        if (mb_strlen($site['main_site_name']) > 120) {
            $errors['main_site_name'] = 'Max 120 characters.';
        }
        if (!in_array($site['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Choose a timezone.';
        }
        if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', $site['language'])) {
            $errors['language'] = 'Use a language code such as en, en-GB or hi-IN.';
        }
        if (!isset(ThemeService::PRESETS[$site['preset']])) {
            $errors['preset'] = 'Choose a theme preset.';
        }
        if ($errors) {
            return ['site', $errors];
        }
        $_SESSION['data']['site'] = $site;
        return ['admin', []];
    }

    private function saveAdmin(array $in): array
    {
        $admin = [
            'name' => trim((string) ($in['name'] ?? '')),
            'email' => strtolower(trim((string) ($in['email'] ?? ''))),
            'password' => (string) ($in['password'] ?? ''),
        ];
        $errors = [];
        if ($admin['name'] === '' || mb_strlen($admin['name']) > 120) {
            $errors['name'] = 'Enter your name.';
        }
        if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL) || strlen($admin['email']) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        }
        $problems = Validator::passwordProblems($admin['password'], 10, [$admin['name'], $admin['email'], explode('@', $admin['email'])[0]]);
        if ($problems) {
            $errors['password'] = implode(' ', $problems);
        } elseif (!hash_equals($admin['password'], (string) ($in['password_confirm'] ?? ''))) {
            $errors['password_confirm'] = 'The passwords do not match.';
        }
        if ($errors) {
            return ['admin', $errors];
        }
        $_SESSION['data']['admin'] = $admin;
        return ['finish', []];
    }

    private function finish(): array
    {
        $data = $_SESSION['data'];
        if (!isset($data['db'], $data['site'], $data['admin'])) {
            return ['requirements', ['_form' => 'Installation data is incomplete. Please start again.']];
        }
        $lock = @fopen(BF_ROOT . '/storage/install.lock.tmp', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['finish', ['_form' => 'Another installation is in progress.']];
        }
        try {
            if (is_file(self::lockFile())) {
                return ['finish', ['_form' => 'BlogFlow is already installed.']];
            }
            $db = new Database($data['db']);
            Seeder::install($db, $data['site'], $data['admin']);

            $config = [
                'db' => [
                    'host' => $data['db']['host'],
                    'port' => $data['db']['port'],
                    'name' => $data['db']['name'],
                    'user' => $data['db']['user'],
                    'pass' => $data['db']['pass'],
                    'prefix' => $data['db']['prefix'],
                ],
                'app' => [
                    'key' => base64_encode(random_bytes(32)),
                    'debug' => false,
                    'installed_at' => gmdate('c'),
                    // Optional hard override of the canonical blog URL (otherwise URL settings are used).
                    'url' => '',
                ],
            ];
            if (!Config::write(Config::path(), $config)) {
                return ['finish', ['_form' => 'The tables were created but the configuration file could not be written to ' . dirname(Config::path()) . '. Make the folder writable and try again with an empty database.']];
            }
            Cache::writeAtomic(self::lockFile(), 'Installed ' . gmdate('c') . "\nDelete this file only if you intend to reinstall BlogFlow CMS.\n");
        } catch (\Throwable $e) {
            \BlogFlow\Core\Logger::error($e);
            return ['finish', ['_form' => 'Installation failed: ' . ($e instanceof \PDOException ? 'a database error occurred.' : $e->getMessage()) . ' See storage/logs for details.']];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink(BF_ROOT . '/storage/install.lock.tmp');
        }
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $this->basePath . '/admin/login?installed=1', true, 303);
        exit;
    }
}
