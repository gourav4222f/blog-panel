<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Models\Tags;
use BlogFlow\Models\Terms;

final class TagController extends TermController
{
    protected function repo(): Terms
    {
        return $this->app->service(Tags::class);
    }

    protected function meta(): array
    {
        return ['singular' => 'Tag', 'plural' => 'Tags', 'route' => 'tags', 'name_field' => 'name'];
    }

    protected function publicUrl(string $slug): string
    {
        return $this->app->url->tag($slug);
    }

    protected function publicPath(string $slug): string
    {
        return '/' . $this->app->url->tagBase() . '/' . $slug;
    }

    protected function fields(?array $row): array
    {
        return [
            'name' => ['type' => 'text', 'label' => 'Name', 'required' => true, 'max' => 120, 'section' => 'Details'],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'max' => 190, 'help' => 'Leave empty to generate from the name.'],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'max' => 2000],
        ] + $this->seoFields();
    }
}
