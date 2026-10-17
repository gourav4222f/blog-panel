<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Models\Categories;
use BlogFlow\Models\Terms;

final class CategoryController extends TermController
{
    protected function repo(): Terms
    {
        return $this->app->service(Categories::class);
    }

    protected function meta(): array
    {
        return ['singular' => 'Category', 'plural' => 'Categories', 'route' => 'categories', 'name_field' => 'name'];
    }

    protected function publicUrl(string $slug): string
    {
        return $this->app->url->category($slug);
    }

    protected function publicPath(string $slug): string
    {
        return '/' . $this->app->url->categoryBase() . '/' . $slug;
    }

    protected function fields(?array $row): array
    {
        $parents = ['' => '— None (top level) —'];
        $exclude = $row ? $this->app->service(Categories::class)->descendantIds((int) $row['id']) : [];
        foreach ($this->app->service(Categories::class)->options() as $id => $name) {
            if (!in_array($id, $exclude, true)) {
                $parents[(string) $id] = $name;
            }
        }
        return [
            'name' => ['type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 120, 'section' => 'Details'],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'max' => 190, 'help' => 'Leave empty to generate from the name.'],
            'parent_id' => ['type' => 'select', 'label' => 'Parent category', 'options' => $parents, 'default' => ''],
            'sort_order' => ['type' => 'int', 'label' => 'Display order', 'default' => 0, 'min' => -1000, 'max' => 1000, 'help' => 'Lower numbers appear first in navigation.'],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'max' => 2000, 'help' => 'Shown at the top of the category archive.'],
            'image_id' => ['type' => 'media', 'label' => 'Image'],
        ] + $this->seoFields();
    }

    protected function prepare(array &$data, ?array $row): array
    {
        $data['parent_id'] = (int) ($data['parent_id'] ?? 0) ?: null;
        if ($row && $data['parent_id'] && in_array($data['parent_id'], $this->app->service(Categories::class)->descendantIds((int) $row['id']), true)) {
            return ['parent_id' => 'A category cannot be placed inside itself or its children.'];
        }
        return [];
    }

    public function reorder(): Response
    {
        foreach ((array) ($this->request->post['order'] ?? []) as $i => $id) {
            $this->db->update('categories', ['sort_order' => (int) $i], 'id = ?', [(int) $id]);
        }
        \BlogFlow\Services\PageCache::clear();
        return $this->json(['ok' => true]);
    }
}
