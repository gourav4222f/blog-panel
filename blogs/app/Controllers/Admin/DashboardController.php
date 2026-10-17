<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;
use BlogFlow\Services\ActivityLog;
use BlogFlow\Services\ImageService;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $auth = $this->app->auth;
        $user = $auth->user();
        $own = !$auth->can('posts.edit_all');
        $counts = $this->app->service(Posts::class)->counts($own ? (int) $user['id'] : null, $own ? (int) $user['author_profile_id'] : null);
        $ownWhere = $own ? ' AND (p.created_by = ' . (int) $user['id'] . ' OR p.author_id = ' . (int) ($user['author_profile_id'] ?? 0) . ')' : '';

        $recent = $this->db->all(
            'SELECT p.id, p.title, p.status, p.updated_at, p.published_at, u.name AS editor FROM {{posts}} p
             LEFT JOIN {{users}} u ON u.id = p.updated_by WHERE p.deleted_at IS NULL' . $ownWhere . ' ORDER BY p.updated_at DESC LIMIT 6'
        );
        $scheduled = $this->db->all(
            "SELECT p.id, p.title, p.published_at FROM {{posts}} p WHERE p.deleted_at IS NULL AND p.status = 'scheduled'" . $ownWhere . ' ORDER BY p.published_at ASC LIMIT 5'
        );
        $seo = [];
        foreach ($this->db->all('SELECT severity, COUNT(*) AS n FROM {{seo_audit_results}} GROUP BY severity') as $r) {
            $seo[$r['severity']] = (int) $r['n'];
        }
        $lastBackup = null;
        foreach (glob(BF_ROOT . '/storage/backups/*.sql.gz') ?: [] as $f) {
            $lastBackup = max($lastBackup ?? 0, filemtime($f));
        }

        return $this->page('dashboard', [
            'pageTitle' => 'Dashboard',
            'counts' => $counts,
            'categories' => (int) $this->db->value('SELECT COUNT(*) FROM {{categories}}'),
            'tags' => (int) $this->db->value('SELECT COUNT(*) FROM {{tags}}'),
            'mediaCount' => (int) $this->db->value('SELECT COUNT(*) FROM {{media}}'),
            'mediaBytes' => (int) $this->db->value('SELECT COALESCE(SUM(filesize), 0) FROM {{media}}'),
            'mediaSaved' => (int) $this->db->value('SELECT COALESCE(SUM(GREATEST(original_filesize - filesize, 0)), 0) FROM {{media}} WHERE optimized_at IS NOT NULL'),
            'recent' => $recent,
            'scheduled' => $scheduled,
            'activity' => $auth->can('activity.view') ? ActivityLog::recent($this->app, 8) : [],
            'seo' => $seo,
            'lastAudit' => $this->app->settings->get('system.last_audit_at'),
            'lastBackup' => $lastBackup,
            'ga4' => $this->app->settings->str('integrations.ga4_id'),
            'gtm' => $this->app->settings->str('integrations.gtm_id'),
            'caps' => ImageService::capabilities(),
            'redirects' => (int) $this->db->value('SELECT COUNT(*) FROM {{redirects}} WHERE is_enabled = 1'),
        ]);
    }

    public function activity(): Response
    {
        $page = $this->pageNumber();
        $per = 50;
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {{activity_logs}}');
        return $this->page('activity', [
            'pageTitle' => 'Activity log',
            'items' => ActivityLog::recent($this->app, $per, ($page - 1) * $per),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per)),
            'total' => $total,
        ]);
    }
}
