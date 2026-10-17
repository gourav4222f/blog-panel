<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Categories;
use BlogFlow\Services\MenuService;

final class MenuController extends AdminController
{
    public function index(): Response
    {
        $location = (string) ($this->request->query['location'] ?? 'header');
        if (!isset(MenuService::LOCATIONS[$location])) {
            $location = 'header';
        }
        $menus = $this->app->service(MenuService::class);
        return $this->page('appearance/menus', [
            'pageTitle' => 'Menu builder',
            'location' => $location,
            'items' => $menus->items($location),
            'categories' => $this->app->service(Categories::class)->options(),
            'tags' => $this->db->all('SELECT id, name FROM {{tags}} ORDER BY name LIMIT 500'),
            'posts' => $this->db->all("SELECT id, title FROM {{posts}} WHERE deleted_at IS NULL AND status = 'published' ORDER BY published_at DESC LIMIT 300"),
            'mainUrl' => $this->app->settings->str('general.main_site_url'),
        ]);
    }

    public function save(string $location): Response
    {
        if (!isset(MenuService::LOCATIONS[$location])) {
            throw new HttpException(404);
        }
        $items = (array) ($this->request->post['items'] ?? []);
        if (count($items) > 60) {
            return $this->json(['ok' => false, 'error' => 'A menu can contain at most 60 links.'], 422);
        }
        $errors = $this->app->service(MenuService::class)->save($location, $items);
        if ($errors) {
            return $this->json(['ok' => false, 'error' => (string) reset($errors), 'errors' => $errors], 422);
        }
        $this->log('menu_update', 'menu', null, 'Updated the ' . $location . ' menu');
        return $this->json(['ok' => true]);
    }
}
