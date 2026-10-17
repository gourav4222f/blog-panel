<?php

declare(strict_types=1);

namespace BlogFlow\Services;

/**
 * Theme presets, self-hosted font registry and CSS variable generation.
 * All values come from validated settings, so generated CSS cannot contain arbitrary input.
 */
final class ThemeService
{
    private const RANGE_LATIN = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
    private const RANGE_LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';

    public const FONTS = [
        'system-sans' => ['label' => 'System sans-serif (no download)', 'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'],
        'system-serif' => ['label' => 'System serif (no download)', 'stack' => 'Charter, "Bitstream Charter", "Sitka Text", Cambria, Georgia, serif'],
        'inter' => ['label' => 'Inter', 'family' => 'Inter Variable', 'fallback' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'italic' => true],
        'dm-sans' => ['label' => 'DM Sans', 'family' => 'DM Sans Variable', 'fallback' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'italic' => true],
        'manrope' => ['label' => 'Manrope', 'family' => 'Manrope Variable', 'fallback' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'italic' => false],
        'source-serif-4' => ['label' => 'Source Serif 4', 'family' => 'Source Serif 4 Variable', 'fallback' => 'Charter, Cambria, Georgia, serif', 'italic' => true],
        'lora' => ['label' => 'Lora', 'family' => 'Lora Variable', 'fallback' => 'Cambria, Georgia, serif', 'italic' => true],
        'merriweather' => ['label' => 'Merriweather', 'family' => 'Merriweather Variable', 'fallback' => 'Cambria, Georgia, serif', 'italic' => true],
        'fraunces' => ['label' => 'Fraunces', 'family' => 'Fraunces Variable', 'fallback' => 'Cambria, Georgia, serif', 'italic' => true],
        'playfair-display' => ['label' => 'Playfair Display', 'family' => 'Playfair Display Variable', 'fallback' => 'Cambria, Georgia, serif', 'italic' => true],
    ];

    public const PRESETS = [
        'minimal' => [
            'label' => 'Minimal Editorial',
            'description' => 'Clean, reading-focused, warm accent.',
            'theme' => [
                'color_bg' => '#FAFAF8', 'color_surface' => '#FFFFFF', 'color_text' => '#17202A', 'color_muted' => '#64748B',
                'color_accent' => '#B5815A', 'color_accent_contrast' => '#17202A', 'color_link' => '#8A5A36', 'color_border' => '#E8E8E5',
                'color_footer_bg' => '#FFFFFF', 'color_footer_text' => '#17202A',
                'dark_bg' => '#111418', 'dark_surface' => '#1A1F26', 'dark_text' => '#ECEEF1', 'dark_muted' => '#A3ACB9', 'dark_border' => '#2B323C',
                'radius' => 10, 'card_style' => 'bordered', 'button_style' => 'solid', 'image_ratio' => '16:10', 'hero_ratio' => '16:9',
            ],
            'typography' => [
                'font_heading' => 'source-serif-4', 'font_body' => 'source-serif-4', 'font_ui' => 'inter',
                'article_size' => 19, 'line_height' => '1.75', 'heading_weight' => '600', 'heading_scale' => '1.25', 'letter_spacing' => '-0.01em',
            ],
        ],
        'soft' => [
            'label' => 'Soft Premium',
            'description' => 'Elegant boutique / hospitality feel.',
            'theme' => [
                'color_bg' => '#F7F3EE', 'color_surface' => '#FFFDFA', 'color_text' => '#2B2420', 'color_muted' => '#74675E',
                'color_accent' => '#94664A', 'color_accent_contrast' => '#FFFFFF', 'color_link' => '#80522F', 'color_border' => '#E7DED4',
                'color_footer_bg' => '#EFE8DF', 'color_footer_text' => '#2B2420',
                'dark_bg' => '#171412', 'dark_surface' => '#211D1A', 'dark_text' => '#F1EAE3', 'dark_muted' => '#B5A89D', 'dark_border' => '#352E29',
                'radius' => 18, 'card_style' => 'shadow', 'button_style' => 'pill', 'image_ratio' => '4:3', 'hero_ratio' => '3:2',
            ],
            'typography' => [
                'font_heading' => 'fraunces', 'font_body' => 'lora', 'font_ui' => 'dm-sans',
                'article_size' => 19, 'line_height' => '1.8', 'heading_weight' => '500', 'heading_scale' => '1.25', 'letter_spacing' => '-0.01em',
            ],
        ],
        'corporate' => [
            'label' => 'Modern Corporate',
            'description' => 'Structured, professional, high clarity.',
            'theme' => [
                'color_bg' => '#F6F8FB', 'color_surface' => '#FFFFFF', 'color_text' => '#0F172A', 'color_muted' => '#556070',
                'color_accent' => '#1D4ED8', 'color_accent_contrast' => '#FFFFFF', 'color_link' => '#1D4ED8', 'color_border' => '#E2E8F0',
                'color_footer_bg' => '#0F172A', 'color_footer_text' => '#E2E8F0',
                'dark_bg' => '#0B1120', 'dark_surface' => '#111A2E', 'dark_text' => '#E5E9F0', 'dark_muted' => '#9AA6B8', 'dark_border' => '#22304A',
                'radius' => 6, 'card_style' => 'bordered', 'button_style' => 'solid', 'image_ratio' => '16:9', 'hero_ratio' => '16:9',
            ],
            'typography' => [
                'font_heading' => 'manrope', 'font_body' => 'inter', 'font_ui' => 'inter',
                'article_size' => 18, 'line_height' => '1.7', 'heading_weight' => '700', 'heading_scale' => '1.25', 'letter_spacing' => '-0.02em',
            ],
        ],
    ];

    public static function fontOptions(): array
    {
        return array_map(fn ($f) => $f['label'], self::FONTS);
    }

    public static function presetOptions(): array
    {
        return array_map(fn ($p) => $p['label'], self::PRESETS);
    }

    public static function stack(string $font): string
    {
        $f = self::FONTS[$font] ?? self::FONTS['system-sans'];
        return isset($f['family']) ? '"' . $f['family'] . '", ' . $f['fallback'] : $f['stack'];
    }

    /** @return string[] font keys that need downloading. */
    public static function usedFonts(array $typography): array
    {
        $keys = array_unique([$typography['font_heading'] ?? '', $typography['font_body'] ?? '', $typography['font_ui'] ?? '']);
        return array_values(array_filter($keys, fn ($k) => isset(self::FONTS[$k]['family'])));
    }

    /** @font-face rules for the given fonts. $fontsUrl is the public assets/fonts URL. */
    public static function fontFaceCss(array $fonts, string $fontsUrl): string
    {
        $css = '';
        foreach ($fonts as $key) {
            $f = self::FONTS[$key] ?? null;
            if (!$f || !isset($f['family'])) {
                continue;
            }
            $styles = $f['italic'] ? ['normal', 'italic'] : ['normal'];
            foreach ($styles as $style) {
                foreach (['latin' => self::RANGE_LATIN, 'latin-ext' => self::RANGE_LATIN_EXT] as $subset => $range) {
                    $css .= sprintf(
                        "@font-face{font-family:'%s';font-style:%s;font-display:swap;font-weight:100 900;src:url(%s/%s-%s-wght-%s.woff2) format('woff2-variations');unicode-range:%s}\n",
                        $f['family'],
                        $style,
                        $fontsUrl,
                        $key,
                        $subset,
                        $style,
                        $range
                    );
                }
            }
        }
        return $css;
    }

    /** Main body font file to preload (latin, normal). */
    public static function preloadFont(array $typography): ?string
    {
        $key = $typography['font_body'] ?? '';
        return isset(self::FONTS[$key]['family']) ? $key . '-latin-wght-normal.woff2' : null;
    }

    private static function ratio(string $r): string
    {
        return preg_match('/^(\d+):(\d+)$/', $r, $m) ? $m[1] . ' / ' . $m[2] : 'auto';
    }

    /** CSS custom properties for the theme. */
    public static function variablesCss(array $t, array $ty): string
    {
        $c = fn (string $k, string $d) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($t[$k] ?? '')) ? $t[$k] : $d;
        $num = fn ($v, $min, $max, $d) => is_numeric($v) ? max($min, min($max, (float) $v)) : $d;
        $vars = [
            '--bf-bg' => $c('color_bg', '#FAFAF8'),
            '--bf-surface' => $c('color_surface', '#FFFFFF'),
            '--bf-text' => $c('color_text', '#17202A'),
            '--bf-muted' => $c('color_muted', '#64748B'),
            '--bf-accent' => $c('color_accent', '#B5815A'),
            '--bf-accent-contrast' => $c('color_accent_contrast', '#17202A'),
            '--bf-link' => $c('color_link', '#8A5A36'),
            '--bf-border' => $c('color_border', '#E8E8E5'),
            '--bf-footer-bg' => $c('color_footer_bg', '#FFFFFF'),
            '--bf-footer-text' => $c('color_footer_text', '#17202A'),
            '--bf-radius' => (int) $num($t['radius'] ?? 10, 0, 28, 10) . 'px',
            '--bf-container' => (int) $num($t['container_width'] ?? 1180, 960, 1440, 1180) . 'px',
            '--bf-article' => (int) $num($t['article_width'] ?? 740, 600, 860, 740) . 'px',
            '--bf-cols' => (string) (int) $num($t['grid_columns'] ?? 3, 2, 4, 3),
            '--bf-card-ratio' => self::ratio((string) ($t['image_ratio'] ?? '16:10')),
            '--bf-hero-ratio' => self::ratio((string) ($t['hero_ratio'] ?? '16:9')),
            '--bf-logo-h' => (int) $num($t['logo_height'] ?? 32, 16, 96, 32) . 'px',
            '--bf-font-heading' => self::stack((string) ($ty['font_heading'] ?? 'system-serif')),
            '--bf-font-body' => self::stack((string) ($ty['font_body'] ?? 'system-serif')),
            '--bf-font-ui' => self::stack((string) ($ty['font_ui'] ?? 'system-sans')),
            '--bf-base-size' => (int) $num($ty['base_size'] ?? 16, 14, 20, 16) . 'px',
            '--bf-article-size' => (int) $num($ty['article_size'] ?? 19, 15, 24, 19) . 'px',
            '--bf-line-height' => (string) $num($ty['line_height'] ?? 1.75, 1.4, 2, 1.75),
            '--bf-heading-weight' => (string) (int) $num($ty['heading_weight'] ?? 600, 300, 900, 600),
            '--bf-scale' => (string) $num($ty['heading_scale'] ?? 1.25, 1.1, 1.5, 1.25),
            '--bf-tracking' => in_array($ty['letter_spacing'] ?? '', ['0', '-0.01em', '-0.02em'], true) ? $ty['letter_spacing'] : '-0.01em',
        ];
        $css = ':root{' . self::join($vars) . '}';
        if (($t['dark_mode'] ?? 'off') === 'auto') {
            $dark = [
                '--bf-bg' => $c('dark_bg', '#111418'),
                '--bf-surface' => $c('dark_surface', '#1A1F26'),
                '--bf-text' => $c('dark_text', '#ECEEF1'),
                '--bf-muted' => $c('dark_muted', '#A3ACB9'),
                '--bf-border' => $c('dark_border', '#2B323C'),
                '--bf-link' => $c('color_accent', '#B5815A'),
                '--bf-footer-bg' => $c('dark_surface', '#1A1F26'),
                '--bf-footer-text' => $c('dark_text', '#ECEEF1'),
            ];
            $css .= '@media (prefers-color-scheme: dark){:root{' . self::join($dark) . ';color-scheme:dark}}';
        }
        return $css;
    }

    private static function join(array $vars): string
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $out[] = $k . ':' . str_replace(['<', '>', '{', '}', ';'], '', (string) $v);
        }
        return implode(';', $out);
    }

    /** Body classes derived from theme settings. */
    public static function bodyClasses(array $t, array $layout): string
    {
        $card = in_array($t['card_style'] ?? '', ['bordered', 'shadow', 'flat'], true) ? $t['card_style'] : 'bordered';
        $btn = in_array($t['button_style'] ?? '', ['solid', 'outline', 'pill'], true) ? $t['button_style'] : 'solid';
        $header = in_array($layout['header_layout'] ?? '', ['split', 'centered', 'stacked'], true) ? $layout['header_layout'] : 'split';
        $classes = ['bf-cards-' . $card, 'bf-btn-' . $btn, 'bf-header-' . $header];
        if (!empty($layout['header_sticky'])) {
            $classes[] = 'bf-header-sticky';
        }
        return implode(' ', $classes);
    }

    /** WCAG relative-luminance contrast ratio between two hex colours. */
    public static function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $hex = ltrim($hex, '#');
            $rgb = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
            $lin = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $rgb);
            return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
        };
        $l1 = $lum($a);
        $l2 = $lum($b);
        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    }

    /** @return string[] readability warnings for a theme. */
    public static function contrastWarnings(array $t): array
    {
        $checks = [
            ['color_text', 'color_bg', 'Primary text on page background', 4.5],
            ['color_text', 'color_surface', 'Primary text on surfaces', 4.5],
            ['color_muted', 'color_surface', 'Muted text on surfaces', 4.5],
            ['color_link', 'color_bg', 'Links and accent text on background', 4.5],
            ['color_link', 'color_surface', 'Links and accent text on surfaces', 4.5],
            ['color_accent_contrast', 'color_accent', 'Button text on accent', 4.5],
            ['color_footer_text', 'color_footer_bg', 'Footer text on footer background', 4.5],
        ];
        $out = [];
        foreach ($checks as [$fg, $bg, $label, $min]) {
            if (!isset($t[$fg], $t[$bg]) || !preg_match('/^#[0-9A-F]{6}$/i', $t[$fg]) || !preg_match('/^#[0-9A-F]{6}$/i', $t[$bg])) {
                continue;
            }
            $ratio = self::contrast($t[$fg], $t[$bg]);
            if ($ratio < $min) {
                $out[] = sprintf('%s has a contrast ratio of %.2f:1 (WCAG AA needs %.1f:1).', $label, $ratio, $min);
            }
        }
        return $out;
    }
}
