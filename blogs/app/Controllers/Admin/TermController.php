<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Terms;
use BlogFlow\Services\PageCache;
use BlogFlow\Services\RedirectService;
use BlogFlow\Validation\Validator;

/**
 * Shared CRUD for categories, tags and authors.
 */
abstract class TermController extends AdminController
{
    abstract protected function repo(): Terms;

    /** @return array{singular: string, plural: string, route: string, name_field: string} */
    abstract protected function meta(): array;

    /** Field definitions (same format as SettingsSchema fields). */
    abstract protected function fields(?array $row): array;

    abstract protected function publicUrl(string $slug): string;

    /** Path relative to the blog root, used for slug-change redirects. */
    abstract protected function publicPath(string $slug): string;

    protected function seoFields(): array
    {
        return [
            'seo_title' => ['type' => 'text', 'label' => 'SEO title', 'max' => 255, 'section' => 'Search appearance', 'help' => 'Leave empty to use the title template from Global metadata.'],
            'seo_description' => ['type' => 'textarea', 'label' => 'Meta description', 'max' => 500, 'help' => 'Leave empty to use the description.'],
            'robots' => ['type' => 'select', 'label' => 'Indexing', 'options' => ['default' => 'Use the global archive setting', 'index' => 'index', 'noindex' => 'noindex'], 'default' => 'default'],
        ];
    }

    /** Extra validation / transformation; return errors. */
    protected function prepare(array &$data, ?array $row): array
    {
        return [];
    }

    protected function afterSave(int $id, array $data): void
    {
    }

    public function index(): Response
    {
        $search = trim((string) ($this->request->query['q'] ?? ''));
        $rows = $this->repo()->withCounts($search);
        if (method_exists($this->repo(), 'tree') && $search === '') {
            $rows = $this->repo()->tree($rows);
        }
        return $this->page('terms/index', [
            'pageTitle' => $this->meta()['plural'],
            'meta' => $this->meta(),
            'rows' => $rows,
            'search' => $search,
            'publicUrl' => fn ($slug) => $this->publicUrl($slug),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(string $id): Response
    {
        $row = $this->repo()->find($this->id($id)) ?? throw new HttpException(404);
        return $this->form($row);
    }

    protected function form(?array $row, array $errors = [], ?array $input = null, int $status = 200): Response
    {
        $values = $input ?? $this->rowValues($row);
        return $this->page('terms/form', [
            'pageTitle' => ($row ? 'Edit ' : 'New ') . strtolower($this->meta()['singular']),
            'meta' => $this->meta(),
            'row' => $row,
            'fields' => $this->fields($row),
            'values' => $values,
            'errors' => $errors,
            'publicUrl' => $row ? $this->publicUrl($row['slug']) : null,
            'count' => $row ? $this->repo()->publishedCount((int) $row['id']) : 0,
        ], $status);
    }

    protected function rowValues(?array $row): array
    {
        $values = [];
        foreach ($this->fields($row) as $k => $f) {
            $values[$k] = $row[$k] ?? ($f['default'] ?? '');
        }
        return $values;
    }

    public function store(): Response
    {
        return $this->save(null);
    }

    public function update(string $id): Response
    {
        $row = $this->repo()->find($this->id($id)) ?? throw new HttpException(404);
        return $this->save($row);
    }

    protected function save(?array $row): Response
    {
        $fields = $this->fields($row);
        $input = (array) ($this->request->post['values'] ?? []);
        $data = [];
        $errors = [];
        foreach ($fields as $k => $f) {
            $raw = $input[$k] ?? null;
            $value = is_scalar($raw) ? trim((string) $raw) : '';
            if ($f['type'] === 'media' || $f['type'] === 'int_ref') {
                $data[$k] = (int) $value > 0 ? (int) $value : null;
                continue;
            }
            if ($f['type'] === 'int') {
                $data[$k] = (int) $value;
                continue;
            }
            if (!empty($f['required']) && $value === '') {
                $errors[$k] = $f['label'] . ' is required.';
            } elseif (isset($f['max']) && mb_strlen($value) > $f['max']) {
                $errors[$k] = sprintf('%s must be at most %d characters.', $f['label'], $f['max']);
            } elseif ($f['type'] === 'url' && $value !== '' && !Validator::isHttpUrl($value)) {
                $errors[$k] = $f['label'] . ' must be a valid URL.';
            } elseif ($f['type'] === 'select' && !array_key_exists($value, $f['options'])) {
                $errors[$k] = 'Choose a valid option.';
            }
            $data[$k] = $value === '' ? null : $value;
        }
        $nameField = $this->meta()['name_field'];
        $slug = strtolower((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->repo()->uniqueSlug((string) ($data[$nameField] ?? ''), $row['id'] ?? null);
        } elseif (!Validator::isSlug($slug)) {
            $errors['slug'] = 'Use lowercase letters, numbers and hyphens.';
        } elseif ($this->repo()->slugTaken($slug, $row['id'] ?? null)) {
            $errors['slug'] = 'This slug is already in use.';
        }
        $data['slug'] = $slug;
        $errors += $this->prepare($data, $row);
        if ($errors) {
            $this->flash('error', 'Please correct the highlighted fields.');
            return $this->form($row, $errors, $input, 422);
        }
        if ($row) {
            $this->repo()->update((int) $row['id'], $data);
            $id = (int) $row['id'];
            if ($row['slug'] !== $slug && $this->repo()->publishedCount($id) > 0) {
                $this->app->service(RedirectService::class)->slugChanged($this->publicPath($row['slug']), $this->publicPath($slug), $this->meta()['singular'] . ' slug changed');
                $this->flash('info', 'A 301 redirect from the old archive URL was created.');
            }
        } else {
            $id = $this->repo()->create($data);
        }
        $this->afterSave($id, $data);
        $this->app->cache->forget('menu:header');
        $this->app->cache->forget('menu:footer');
        PageCache::clear();
        $this->log(strtolower($this->meta()['singular']) . ($row ? '_update' : '_create'), strtolower($this->meta()['singular']), $id, ($row ? 'Updated ' : 'Created ') . strtolower($this->meta()['singular']) . ' “' . $data[$nameField] . '”');
        $this->flash('success', $this->meta()['singular'] . ($row ? ' updated.' : ' created.'));
        return $this->redirect($this->app->url->admin($this->meta()['route'] . '/' . $id . '/edit'));
    }

    public function delete(string $id): Response
    {
        $row = $this->repo()->find($this->id($id)) ?? throw new HttpException(404);
        $this->repo()->delete((int) $row['id']);
        PageCache::clear();
        $this->log(strtolower($this->meta()['singular']) . '_delete', strtolower($this->meta()['singular']), (int) $row['id'], 'Deleted ' . strtolower($this->meta()['singular']) . ' “' . $row[$this->meta()['name_field']] . '”');
        $this->flash('success', $this->meta()['singular'] . ' deleted. Posts that used it were kept.');
        return $this->redirect($this->app->url->admin($this->meta()['route']));
    }
}
