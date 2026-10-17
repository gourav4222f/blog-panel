<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Models\Authors;
use BlogFlow\Models\Terms;

final class AuthorController extends TermController
{
    protected function repo(): Terms
    {
        return $this->app->service(Authors::class);
    }

    protected function meta(): array
    {
        return ['singular' => 'Author', 'plural' => 'Authors', 'route' => 'authors', 'name_field' => 'display_name'];
    }

    protected function publicUrl(string $slug): string
    {
        return $this->app->url->author($slug);
    }

    protected function publicPath(string $slug): string
    {
        return '/' . $this->app->url->authorBase() . '/' . $slug;
    }

    protected function fields(?array $row): array
    {
        $fields = [
            'display_name' => ['type' => 'text', 'label' => 'Public display name', 'required' => true, 'max' => 120, 'section' => 'Profile'],
            'name' => ['type' => 'text', 'label' => 'Full name (internal)', 'max' => 120, 'help' => 'Defaults to the display name.'],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'max' => 190, 'help' => 'Used in the author page URL.'],
            'job_title' => ['type' => 'text', 'label' => 'Job title / role', 'max' => 120],
            'bio' => ['type' => 'textarea', 'label' => 'Biography', 'max' => 3000, 'help' => 'Shown on the author page and below articles.'],
            'photo_id' => ['type' => 'media', 'label' => 'Profile photo'],
            'website_url' => ['type' => 'url', 'label' => 'Profile / website URL', 'section' => 'Links'],
        ];
        foreach (Authors::SOCIAL_FIELDS as $k => $label) {
            $fields['social_' . $k] = ['type' => 'url', 'label' => $label];
        }
        if ($this->app->auth->can('users.manage')) {
            $users = ['' => '— Not linked —'];
            foreach ($this->db->all('SELECT u.id, u.name, u.email FROM {{users}} u LEFT JOIN {{authors}} a ON a.user_id = u.id WHERE a.id IS NULL OR a.id = ? ORDER BY u.name', [(int) ($row['id'] ?? 0)]) as $u) {
                $users[(string) $u['id']] = $u['name'] . ' (' . $u['email'] . ')';
            }
            $fields['user_id'] = ['type' => 'select', 'label' => 'Linked user account', 'options' => $users, 'default' => '', 'section' => 'Account', 'help' => 'Authors with a linked account publish under this profile.'];
        }
        return $fields + $this->seoFields();
    }

    protected function rowValues(?array $row): array
    {
        $values = parent::rowValues($row);
        $social = $row ? Authors::social($row) : [];
        foreach (Authors::SOCIAL_FIELDS as $k => $label) {
            $values['social_' . $k] = $social[$k] ?? '';
        }
        return $values;
    }

    protected function prepare(array &$data, ?array $row): array
    {
        $social = [];
        foreach (array_keys(Authors::SOCIAL_FIELDS) as $k) {
            if (!empty($data['social_' . $k])) {
                $social[$k] = $data['social_' . $k];
            }
            unset($data['social_' . $k]);
        }
        $data['social'] = $social ? json_encode($social, JSON_UNESCAPED_SLASHES) : null;
        $data['name'] = $data['name'] ?: $data['display_name'];
        if (array_key_exists('user_id', $data)) {
            $data['user_id'] = (int) $data['user_id'] ?: null;
            if ($data['user_id'] && $this->db->value('SELECT 1 FROM {{authors}} WHERE user_id = ? AND id <> ?', [$data['user_id'], (int) ($row['id'] ?? 0)])) {
                return ['user_id' => 'That user already has an author profile.'];
            }
        }
        return [];
    }
}
