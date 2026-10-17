<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Services\MenuService;
use BlogFlow\Services\PageCache;
use BlogFlow\Services\Settings;
use BlogFlow\Services\SettingsSchema;
use BlogFlow\Services\ThemeService;

final class AppearanceController extends AdminController
{
    private const PAGES = [
        'theme' => ['Theme settings', 'Brand assets, colours, shapes and layout. Changes never touch your content.'],
        'typography' => ['Typography', 'Self-hosted fonts and reading comfort.'],
        'layout' => ['Header & footer', 'Header layout, call-to-action and footer details. Links are managed in the menu builder.'],
    ];

    public function theme(): Response
    {
        return $this->handle('theme');
    }

    public function typography(): Response
    {
        return $this->handle('typography');
    }

    public function layout(): Response
    {
        return $this->handle('layout');
    }

    private function handle(string $group): Response
    {
        $errors = [];
        $values = $this->app->settings->group($group);
        if ($this->request->isPost()) {
            $input = (array) ($this->request->post['values'] ?? []);
            [$clean, $errors] = Settings::validateGroup($group, $input, $values, false);
            if (!$errors) {
                $rows = [];
                foreach ($clean as $k => $v) {
                    $rows[$group . '.' . $k] = $v;
                }
                $this->app->settings->setMany($rows);
                $this->app->service(MenuService::class)->clearCache();
                PageCache::clear();
                $this->log('appearance_update', 'settings', null, 'Updated ' . self::PAGES[$group][0]);
                $this->flash('success', 'Appearance saved.');
                foreach ($group === 'theme' ? ThemeService::contrastWarnings($clean) : [] as $w) {
                    $this->flash('warning', $w);
                }
                return $this->redirect($this->app->url->admin('appearance/' . $group));
            }
            $values = array_merge($values, $input);
            $this->flash('error', 'Please correct the highlighted fields.');
        }
        $fontsUrl = $this->app->url->basePath() . '/assets/fonts';
        return $this->page('settings/form', [
            'pageTitle' => self::PAGES[$group][0],
            'title' => self::PAGES[$group][0],
            'intro' => self::PAGES[$group][1],
            'group' => $group,
            'action' => $this->app->url->admin('appearance/' . $group),
            'schema' => SettingsSchema::group($group),
            'values' => $values,
            'errors' => $errors,
            'caps' => [],
            'isAdmin' => false,
            'preview' => true,
            'previewUrl' => $this->app->url->basePath() . '/',
            'presets' => $group === 'theme' ? ThemeService::PRESETS : [],
            'warnings' => $group === 'theme' ? ThemeService::contrastWarnings($values) : [],
            'fontData' => [
                'stacks' => array_map(fn ($k) => ThemeService::stack($k), array_combine(array_keys(ThemeService::FONTS), array_keys(ThemeService::FONTS))),
                'css' => ThemeService::fontFaceCss(array_keys(ThemeService::FONTS), $fontsUrl),
            ],
        ], $errors ? 422 : 200);
    }

    public function preset(): Response
    {
        $key = (string) ($this->request->post['preset'] ?? '');
        $preset = ThemeService::PRESETS[$key] ?? null;
        if (!$preset) {
            $this->flash('error', 'Unknown preset.');
            return $this->redirect($this->app->url->admin('appearance/theme'));
        }
        $rows = ['theme.preset' => $key];
        foreach ($preset['theme'] as $k => $v) {
            $rows['theme.' . $k] = $v;
        }
        foreach ($preset['typography'] as $k => $v) {
            $rows['typography.' . $k] = $v;
        }
        $this->app->settings->setMany($rows);
        PageCache::clear();
        $this->log('appearance_preset', 'settings', null, 'Applied theme preset ' . $preset['label']);
        $this->flash('success', $preset['label'] . ' preset applied.');
        return $this->redirect($this->app->url->admin('appearance/theme'));
    }
}
