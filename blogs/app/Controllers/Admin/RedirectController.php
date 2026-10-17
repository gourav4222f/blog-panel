<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;
use BlogFlow\Services\RedirectService;

final class RedirectController extends AdminController
{
    private function service(): RedirectService
    {
        return $this->app->service(RedirectService::class);
    }

    public function index(): Response
    {
        $q = trim((string) ($this->request->query['q'] ?? ''));
        $params = [];
        $where = '1 = 1';
        if ($q !== '') {
            $where = '(source_path LIKE ? OR destination LIKE ?)';
            $like = '%' . Posts::likeEscape($q) . '%';
            $params = [$like, $like];
        }
        $edit = isset($this->request->query['edit']) ? $this->db->one('SELECT * FROM {{redirects}} WHERE id = ?', [(int) $this->request->query['edit']]) : null;
        return $this->page('seo/redirects', [
            'pageTitle' => 'Redirects',
            'rows' => $this->db->all("SELECT * FROM {{redirects}} WHERE $where ORDER BY updated_at DESC LIMIT 500", $params),
            'chains' => array_column($this->service()->chains(), 'id'),
            'edit' => $edit,
            'q' => $q,
        ]);
    }

    private function input(): array
    {
        $in = $this->request->post;
        return [
            'source_path' => $this->service()->normalizeSource((string) ($in['source_path'] ?? '')),
            'destination' => $this->service()->normalizeDestination((string) ($in['destination'] ?? '')),
            'status_code' => (int) ($in['status_code'] ?? 301),
            'is_enabled' => $this->request->bool('is_enabled'),
            'note' => trim((string) ($in['note'] ?? '')),
        ];
    }

    public function store(): Response
    {
        $data = $this->input();
        $errors = $this->service()->validate($data['source_path'], $data['destination']);
        if ($errors) {
            return $this->withErrors($this->app->url->admin('seo/redirects'), $errors);
        }
        $id = $this->service()->save($data);
        \BlogFlow\Services\PageCache::clear();
        $this->log('redirect_create', 'redirect', $id, sprintf('Redirect %s → %s (%d)', $data['source_path'], $data['destination'], $data['status_code']));
        $this->flash('success', 'Redirect added.');
        return $this->redirect($this->app->url->admin('seo/redirects'));
    }

    public function update(string $id): Response
    {
        $row = $this->db->one('SELECT * FROM {{redirects}} WHERE id = ?', [$this->id($id)]) ?? throw new HttpException(404);
        $data = $this->input();
        $errors = $this->service()->validate($data['source_path'], $data['destination'], (int) $row['id']);
        if ($errors) {
            return $this->withErrors($this->app->url->admin('seo/redirects', ['edit' => $row['id']]), $errors);
        }
        $this->service()->save($data, (int) $row['id']);
        \BlogFlow\Services\PageCache::clear();
        $this->log('redirect_update', 'redirect', (int) $row['id'], sprintf('Redirect %s → %s', $data['source_path'], $data['destination']));
        $this->flash('success', 'Redirect updated.');
        return $this->redirect($this->app->url->admin('seo/redirects'));
    }

    public function toggle(string $id): Response
    {
        $row = $this->db->one('SELECT * FROM {{redirects}} WHERE id = ?', [$this->id($id)]) ?? throw new HttpException(404);
        if (!$row['is_enabled']) {
            $errors = $this->service()->validate($row['source_path'], $row['destination'], (int) $row['id']);
            if ($errors) {
                $this->flash('error', 'Cannot enable: ' . reset($errors));
                return $this->redirect($this->app->url->admin('seo/redirects'));
            }
        }
        $this->db->update('redirects', ['is_enabled' => $row['is_enabled'] ? 0 : 1, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$row['id']]);
        $this->service()->clearCache();
        $this->flash('success', $row['is_enabled'] ? 'Redirect disabled.' : 'Redirect enabled.');
        return $this->redirect($this->app->url->admin('seo/redirects'));
    }

    public function delete(string $id): Response
    {
        $row = $this->db->one('SELECT * FROM {{redirects}} WHERE id = ?', [$this->id($id)]) ?? throw new HttpException(404);
        $this->db->delete('redirects', 'id = ?', [$row['id']]);
        $this->service()->clearCache();
        $this->log('redirect_delete', 'redirect', (int) $row['id'], 'Deleted redirect ' . $row['source_path']);
        $this->flash('success', 'Redirect deleted.');
        return $this->redirect($this->app->url->admin('seo/redirects'));
    }

    public function flatten(): Response
    {
        $n = $this->service()->flatten();
        $this->log('redirect_flatten', 'redirect', null, "Flattened $n redirect chain(s)");
        $this->flash('success', $n ? "$n redirect(s) now point directly to their final destination." : 'No redirect chains found.');
        return $this->redirect($this->app->url->admin('seo/redirects'));
    }
}
