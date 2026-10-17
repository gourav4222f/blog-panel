<?php

declare(strict_types=1);

namespace BlogFlow\Models;

final class Authors extends Terms
{
    protected string $table = 'authors';
    protected string $nameColumn = 'display_name';
    protected string $postColumn = 'author_id';

    public function forUser(int $userId): ?array
    {
        return $this->db->one('SELECT * FROM {{authors}} WHERE user_id = ?', [$userId]);
    }

    public static function social(array $author): array
    {
        $s = is_string($author['social'] ?? null) ? json_decode($author['social'], true) : ($author['social'] ?? []);
        return is_array($s) ? $s : [];
    }

    public const SOCIAL_FIELDS = ['x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'];
}
