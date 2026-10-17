<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Logger;

final class ActivityLog
{
    public static function record(App $app, string $action, ?string $type = null, ?int $id = null, ?string $summary = null): void
    {
        try {
            $app->db->insert('activity_logs', [
                'user_id' => $app->auth->id(),
                'action' => substr($action, 0, 60),
                'entity_type' => $type,
                'entity_id' => $id,
                'summary' => $summary !== null ? mb_substr($summary, 0, 500) : null,
                'ip' => $app->request->ip(),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error($e);
        }
    }

    public static function recent(App $app, int $limit = 10, int $offset = 0): array
    {
        return $app->db->all(
            'SELECT l.*, u.name AS user_name FROM {{activity_logs}} l LEFT JOIN {{users}} u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }
}
