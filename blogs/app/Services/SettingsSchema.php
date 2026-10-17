<?php

declare(strict_types=1);

namespace BlogFlow\Services;

/**
 * Declarative definition of every configurable site setting.
 *
 * Each group is stored in the settings table as "group.key" rows. Field types drive
 * both validation and the generic admin form renderer.
 *
 * Field options:
 *  - type: text|textarea|url|email|int|bool|select|color|media|code|password|multicheck|timezone
 *  - secret: never exported, never echoed back into forms
 *  - export: false to keep a non-secret value out of configuration exports
 */
final class SettingsSchema
{
    public const DATE_FORMATS = [
        'M j, Y' => 'Oct 17, 2026',
        'F j, Y' => 'October 17, 2026',
        'j M Y' => '17 Oct 2026',
        'j F Y' => '17 October 2026',
        'd/m/Y' => '17/10/2026',
        'm/d/Y' => '10/17/2026',
        'Y-m-d' => '2026-10-17',
    ];

    /** Groups exported by "Export site configuration". */
    public const EXPORTABLE_GROUPS = ['general', 'seo', 'schema', 'sitemap', 'social', 'images', 'theme', 'typography', 'layout', 'integrations'];

    public static function groups(): array
    {
        static $groups = null;
        return $groups ??= self::build();
    }

    public static function group(string $group): array
    {
        return self::groups()[$group] ?? throw new \InvalidArgumentException('Unknown settings group ' . $group);
    }

    /** @return array<string, mixed> flat "group.key" => default */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::groups() as $g => $def) {
            foreach ($def['fields'] as $key => $field) {
                $out[$g . '.' . $key] = $field['default'] ?? null;
            }
        }
        return $out;
    }

    public static function field(string $dotted): ?array
    {
        [$g, $k] = array_pad(explode('.', $dotted, 2), 2, '');
        return self::groups()[$g]['fields'][$k] ?? null;
    }

    private static function build(): array
    {
        $ratio = ['16:10' => '16:10 (landscape)', '3:2' => '3:2 (photo)', '4:3' => '4:3', '16:9' => '16:9 (wide)', '1:1' => '1:1 (square)'];
        $fonts = ThemeService::fontOptions();

        return [
            'general' => [
                'title' => 'General settings',
                'fields' => [
                    'blog_name' => ['type' => 'text', 'label' => 'Blog name', 'default' => 'Blog', 'required' => true, 'max' => 120, 'section' => 'Identity'],
                    'blog_tagline' => ['type' => 'text', 'label' => 'Tagline', 'default' => '', 'max' => 200],
                    'intro_title' => ['type' => 'text', 'label' => 'Homepage heading', 'default' => 'Insights & stories', 'max' => 160, 'help' => 'Shown as the H1 on the blog homepage.'],
                    'intro_text' => ['type' => 'textarea', 'label' => 'Homepage introduction', 'default' => '', 'max' => 600],
                    'main_site_name' => ['type' => 'text', 'label' => 'Main website name', 'default' => '', 'max' => 120, 'section' => 'Main website'],
                    'main_site_url' => ['type' => 'url', 'label' => 'Main website URL', 'default' => '', 'help' => 'Used for the "back to website" links and as the organisation URL.'],
                    'timezone' => ['type' => 'timezone', 'label' => 'Timezone', 'default' => 'UTC', 'section' => 'Locale'],
                    'language' => ['type' => 'text', 'label' => 'Language (BCP 47)', 'default' => 'en', 'max' => 20, 'pattern' => '/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', 'help' => 'Used for the html lang attribute, e.g. en, en-GB, hi-IN.'],
                    'date_format' => ['type' => 'select', 'label' => 'Date format', 'default' => 'M j, Y', 'options' => self::DATE_FORMATS],
                    'posts_per_page' => ['type' => 'int', 'label' => 'Posts per page', 'default' => 9, 'min' => 1, 'max' => 48, 'section' => 'Blog homepage'],
                    'show_featured' => ['type' => 'bool', 'label' => 'Show featured article on the homepage', 'default' => true],
                    'show_category_nav' => ['type' => 'bool', 'label' => 'Show category navigation', 'default' => true],
                    'show_search' => ['type' => 'bool', 'label' => 'Enable search', 'default' => true],
                    'show_author_on_cards' => ['type' => 'bool', 'label' => 'Show author on article cards', 'default' => true],
                    'show_reading_time' => ['type' => 'bool', 'label' => 'Show reading time', 'default' => true],
                    'show_toc' => ['type' => 'bool', 'label' => 'Show table of contents by default', 'default' => true, 'section' => 'Articles', 'help' => 'Only displayed when an article has at least three headings. Can be overridden per post.'],
                    'show_share' => ['type' => 'bool', 'label' => 'Show sharing links', 'default' => true],
                    'show_related' => ['type' => 'bool', 'label' => 'Show related posts', 'default' => true],
                    'related_count' => ['type' => 'int', 'label' => 'Number of related posts', 'default' => 3, 'min' => 1, 'max' => 6],
                    'show_author_bio' => ['type' => 'bool', 'label' => 'Show author biography', 'default' => true],
                    'show_prev_next' => ['type' => 'bool', 'label' => 'Show previous / next article links', 'default' => true],
                    'cta_enabled' => ['type' => 'bool', 'label' => 'Show call-to-action after articles', 'default' => false, 'section' => 'Article call-to-action'],
                    'cta_title' => ['type' => 'text', 'label' => 'CTA heading', 'default' => '', 'max' => 160],
                    'cta_text' => ['type' => 'textarea', 'label' => 'CTA text', 'default' => '', 'max' => 400],
                    'cta_label' => ['type' => 'text', 'label' => 'CTA button label', 'default' => '', 'max' => 60],
                    'cta_url' => ['type' => 'url', 'label' => 'CTA button URL', 'default' => ''],
                    'page_cache' => ['type' => 'bool', 'label' => 'Enable public page cache', 'default' => true, 'section' => 'Performance', 'help' => 'Caches rendered public pages as files. Automatically cleared when content or settings change.'],
                    'page_cache_ttl' => ['type' => 'int', 'label' => 'Page cache lifetime (seconds)', 'default' => 3600, 'min' => 60, 'max' => 86400],
                ],
            ],
            'url' => [
                'title' => 'URL settings',
                'fields' => [
                    'blog_url' => ['type' => 'url', 'label' => 'Blog URL', 'default' => '', 'required' => true, 'help' => 'Absolute URL of this blog installation without a trailing slash, e.g. https://example.com/blogs. Used for canonical URLs, sitemaps and feeds.', 'section' => 'Canonical address'],
                    'force_canonical_host' => ['type' => 'bool', 'label' => 'Redirect other hosts / HTTP to the canonical URL', 'default' => false, 'help' => 'Permanently redirects requests made on a different scheme or host (e.g. http:// or non-www) to the blog URL above. Enable once the blog URL is confirmed correct.'],
                    'trailing_slash' => ['type' => 'select', 'label' => 'Trailing slash policy', 'default' => 'none', 'options' => ['none' => 'No trailing slash (/blogs/my-post)', 'slash' => 'Trailing slash (/blogs/my-post/)'], 'help' => 'The other variant is permanently redirected. The blog homepage always ends with a slash.'],
                    'category_base' => ['type' => 'text', 'label' => 'Category base', 'default' => 'category', 'pattern' => '/^[a-z0-9][a-z0-9-]{0,39}$/', 'section' => 'Archive bases', 'help' => 'Lowercase letters, numbers and hyphens.'],
                    'tag_base' => ['type' => 'text', 'label' => 'Tag base', 'default' => 'tag', 'pattern' => '/^[a-z0-9][a-z0-9-]{0,39}$/'],
                    'author_base' => ['type' => 'text', 'label' => 'Author base', 'default' => 'author', 'pattern' => '/^[a-z0-9][a-z0-9-]{0,39}$/'],
                    'auto_redirect_slug' => ['type' => 'bool', 'label' => 'Create 301 redirects when a published slug changes', 'default' => true, 'section' => 'Redirects'],
                ],
            ],
            'seo' => [
                'title' => 'Global metadata',
                'fields' => [
                    'site_title' => ['type' => 'text', 'label' => 'Blog homepage SEO title', 'default' => '', 'max' => 200, 'help' => 'Leave empty to use "{blog name} | {main site name}".', 'section' => 'Homepage'],
                    'site_description' => ['type' => 'textarea', 'label' => 'Blog homepage meta description', 'default' => '', 'max' => 400],
                    'title_post' => ['type' => 'text', 'label' => 'Post title template', 'default' => '{post_title} | {site_name}', 'max' => 200, 'section' => 'Title templates', 'help' => 'Variables: {post_title}, {site_name}, {category_name}, {author_name}. Used when a post has no custom SEO title.'],
                    'title_category' => ['type' => 'text', 'label' => 'Category title template', 'default' => '{category_name} | {site_name}', 'max' => 200, 'help' => 'Variables: {category_name}, {site_name}, {page_number}.'],
                    'title_tag' => ['type' => 'text', 'label' => 'Tag title template', 'default' => '{tag_name} | {site_name}', 'max' => 200],
                    'title_author' => ['type' => 'text', 'label' => 'Author title template', 'default' => '{author_name} | {site_name}', 'max' => 200],
                    'title_search' => ['type' => 'text', 'label' => 'Search title template', 'default' => 'Search results for “{query}” | {site_name}', 'max' => 200],
                    'title_paged' => ['type' => 'text', 'label' => 'Pagination suffix', 'default' => ' – Page {page_number}', 'max' => 60, 'help' => 'Inserted before the separator on page 2 and beyond.'],
                    'title_separator' => ['type' => 'text', 'label' => 'Separator', 'default' => '|', 'max' => 5],
                    'description_post' => ['type' => 'text', 'label' => 'Post description fallback', 'default' => '{post_excerpt}', 'max' => 200, 'section' => 'Description templates', 'help' => 'Variables: {post_excerpt}, {post_title}, {site_name}. Used when a post has no meta description.'],
                    'description_category' => ['type' => 'text', 'label' => 'Category description fallback', 'default' => '{category_description}', 'max' => 200],
                    'robots_posts_index' => ['type' => 'bool', 'label' => 'Index posts by default', 'default' => true, 'section' => 'Indexing', 'help' => 'Turning this off adds noindex to every post without an explicit override. Leave on for normal blogs.'],
                    'index_categories' => ['type' => 'bool', 'label' => 'Index category archives', 'default' => true],
                    'index_tags' => ['type' => 'bool', 'label' => 'Index tag archives', 'default' => false],
                    'index_authors' => ['type' => 'bool', 'label' => 'Index author archives', 'default' => true],
                    'index_paginated' => ['type' => 'bool', 'label' => 'Index paginated pages (page 2+)', 'default' => true, 'help' => 'Paginated pages always use self-referencing canonicals.'],
                    'index_search' => ['type' => 'bool', 'label' => 'Index search results', 'default' => false, 'help' => 'Not recommended. Search result pages are excluded from the sitemap regardless.'],
                    'max_image_preview' => ['type' => 'select', 'label' => 'max-image-preview', 'default' => 'large', 'options' => ['large' => 'large', 'standard' => 'standard', 'none' => 'none'], 'section' => 'Snippet controls'],
                    'max_snippet' => ['type' => 'int', 'label' => 'max-snippet (-1 = no limit)', 'default' => -1, 'min' => -1, 'max' => 1000],
                    'max_video_preview' => ['type' => 'int', 'label' => 'max-video-preview (-1 = no limit)', 'default' => -1, 'min' => -1, 'max' => 3600],
                    'x_robots_header' => ['type' => 'bool', 'label' => 'Also send X-Robots-Tag headers', 'default' => true],
                    'og_default_image' => ['type' => 'media', 'label' => 'Default social sharing image', 'default' => null, 'section' => 'Social defaults', 'help' => 'Recommended 1200 × 630 px. Used when a page has no image of its own.'],
                    'twitter_card' => ['type' => 'select', 'label' => 'Default X/Twitter card', 'default' => 'summary_large_image', 'options' => ['summary_large_image' => 'Summary with large image', 'summary' => 'Summary']],
                    'twitter_site' => ['type' => 'text', 'label' => 'X/Twitter @username of the site', 'default' => '', 'max' => 30, 'pattern' => '/^@?[A-Za-z0-9_]{1,15}$/'],
                ],
            ],
            'schema' => [
                'title' => 'Schema settings',
                'fields' => [
                    'org_type' => ['type' => 'select', 'label' => 'Publisher type', 'default' => 'Organization', 'options' => ['Organization' => 'Organization', 'Corporation' => 'Corporation', 'LocalBusiness' => 'Local business', 'ProfessionalService' => 'Professional service', 'LegalService' => 'Legal service', 'MedicalOrganization' => 'Medical organization', 'TravelAgency' => 'Travel agency', 'LodgingBusiness' => 'Hotel / lodging', 'RealEstateAgent' => 'Real estate agent', 'EducationalOrganization' => 'Educational organization', 'NGO' => 'Non-profit (NGO)', 'Person' => 'Person (personal blog)'], 'section' => 'Publisher'],
                    'org_name' => ['type' => 'text', 'label' => 'Organisation name', 'default' => '', 'max' => 160, 'help' => 'Defaults to the main website name.'],
                    'org_url' => ['type' => 'url', 'label' => 'Organisation URL', 'default' => '', 'help' => 'Defaults to the main website URL.'],
                    'org_logo' => ['type' => 'media', 'label' => 'Organisation logo', 'default' => null, 'help' => 'Square or rectangular logo, at least 112 × 112 px.'],
                    'org_same_as' => ['type' => 'textarea', 'label' => 'Official profiles (sameAs)', 'default' => '', 'help' => 'One URL per line. Social profile links from Social Media settings are added automatically.'],
                    'post_type' => ['type' => 'select', 'label' => 'Default article schema type', 'default' => 'BlogPosting', 'options' => ['BlogPosting' => 'BlogPosting', 'Article' => 'Article', 'NewsArticle' => 'NewsArticle'], 'section' => 'Articles'],
                    'breadcrumbs' => ['type' => 'bool', 'label' => 'Output BreadcrumbList', 'default' => true],
                    'website' => ['type' => 'bool', 'label' => 'Output WebSite schema on the blog homepage', 'default' => true],
                    'faq' => ['type' => 'bool', 'label' => 'Output FAQPage for posts with an FAQ section', 'default' => true, 'help' => 'Only output when the FAQ is visible on the page. Google currently limits FAQ rich results to well-known, authoritative government and health sites, so rich results are not guaranteed.'],
                ],
            ],
            'sitemap' => [
                'title' => 'Sitemap & feed',
                'fields' => [
                    'include_posts' => ['type' => 'bool', 'label' => 'Include posts', 'default' => true, 'section' => 'XML sitemap'],
                    'include_categories' => ['type' => 'bool', 'label' => 'Include categories (when indexable)', 'default' => true],
                    'include_tags' => ['type' => 'bool', 'label' => 'Include tags (when indexable)', 'default' => true],
                    'include_authors' => ['type' => 'bool', 'label' => 'Include author archives (when indexable)', 'default' => true],
                    'include_images' => ['type' => 'bool', 'label' => 'Include image entries for featured images', 'default' => true],
                    'max_urls' => ['type' => 'int', 'label' => 'URLs per sitemap file', 'default' => 2000, 'min' => 100, 'max' => 50000, 'help' => 'When exceeded, sitemap.xml becomes a sitemap index pointing to split files.'],
                    'feed_items' => ['type' => 'int', 'label' => 'Items in RSS feed', 'default' => 20, 'min' => 5, 'max' => 100, 'section' => 'RSS feed'],
                    'feed_full_content' => ['type' => 'bool', 'label' => 'Include full article content in the feed', 'default' => false],
                ],
            ],
            'social' => [
                'title' => 'Social media',
                'fields' => [
                    'facebook' => ['type' => 'url', 'label' => 'Facebook', 'default' => '', 'section' => 'Profiles'],
                    'instagram' => ['type' => 'url', 'label' => 'Instagram', 'default' => ''],
                    'x' => ['type' => 'url', 'label' => 'X (Twitter)', 'default' => ''],
                    'linkedin' => ['type' => 'url', 'label' => 'LinkedIn', 'default' => ''],
                    'youtube' => ['type' => 'url', 'label' => 'YouTube', 'default' => ''],
                    'pinterest' => ['type' => 'url', 'label' => 'Pinterest', 'default' => ''],
                    'tiktok' => ['type' => 'url', 'label' => 'TikTok', 'default' => ''],
                    'whatsapp' => ['type' => 'url', 'label' => 'WhatsApp link', 'default' => '', 'help' => 'e.g. https://wa.me/15551234567'],
                    'share_networks' => ['type' => 'multicheck', 'label' => 'Share buttons on articles', 'default' => ['x', 'facebook', 'linkedin', 'whatsapp', 'email', 'copy'], 'options' => ['x' => 'X', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'pinterest' => 'Pinterest', 'email' => 'Email', 'copy' => 'Copy link'], 'section' => 'Sharing'],
                ],
            ],
            'images' => [
                'title' => 'Image settings',
                'fields' => [
                    'max_upload_mb' => ['type' => 'int', 'label' => 'Maximum upload size (MB)', 'default' => 10, 'min' => 1, 'max' => 64, 'section' => 'Uploads', 'help' => 'Also limited by the server (upload_max_filesize / post_max_size).'],
                    'max_width' => ['type' => 'int', 'label' => 'Maximum stored width (px)', 'default' => 2400, 'min' => 800, 'max' => 6000, 'help' => 'Larger uploads are resized down. Images are never upscaled.'],
                    'max_pixels' => ['type' => 'int', 'label' => 'Maximum source megapixels', 'default' => 40, 'min' => 4, 'max' => 120, 'help' => 'Protects shared hosting memory limits.'],
                    'keep_originals' => ['type' => 'bool', 'label' => 'Keep original uploads (private) for re-optimisation', 'default' => true],
                    'jpeg_quality' => ['type' => 'int', 'label' => 'JPEG quality', 'default' => 82, 'min' => 40, 'max' => 100, 'section' => 'Compression'],
                    'webp_quality' => ['type' => 'int', 'label' => 'WebP quality', 'default' => 80, 'min' => 40, 'max' => 100],
                    'avif_quality' => ['type' => 'int', 'label' => 'AVIF quality', 'default' => 55, 'min' => 30, 'max' => 100],
                    'png_compression' => ['type' => 'int', 'label' => 'PNG compression level (0–9)', 'default' => 8, 'min' => 0, 'max' => 9],
                    'generate_webp' => ['type' => 'bool', 'label' => 'Generate WebP versions', 'default' => true],
                    'generate_avif' => ['type' => 'bool', 'label' => 'Generate AVIF versions (when supported by the server)', 'default' => true],
                    'strip_metadata' => ['type' => 'bool', 'label' => 'Strip EXIF/location metadata', 'default' => true, 'help' => 'Images are re-encoded which removes metadata; orientation is applied first.'],
                    'size_thumb' => ['type' => 'int', 'label' => 'Thumbnail width', 'default' => 320, 'min' => 120, 'max' => 800, 'section' => 'Responsive sizes'],
                    'size_card' => ['type' => 'int', 'label' => 'Card width', 'default' => 640, 'min' => 320, 'max' => 1200],
                    'size_medium' => ['type' => 'int', 'label' => 'Medium width', 'default' => 960, 'min' => 480, 'max' => 1600],
                    'size_large' => ['type' => 'int', 'label' => 'Large width', 'default' => 1440, 'min' => 800, 'max' => 2400],
                    'oversized_kb' => ['type' => 'int', 'label' => 'Flag images larger than (KB) in the SEO audit', 'default' => 400, 'min' => 50, 'max' => 5000],
                ],
            ],
            'integrations' => [
                'title' => 'Integrations',
                'fields' => [
                    'ga4_id' => ['type' => 'text', 'label' => 'Google Analytics 4 measurement ID', 'default' => '', 'pattern' => '/^G-[A-Z0-9]{4,20}$/', 'section' => 'Analytics', 'help' => 'Format: G-XXXXXXX. Leave empty to disable.'],
                    'gtm_id' => ['type' => 'text', 'label' => 'Google Tag Manager container ID', 'default' => '', 'pattern' => '/^GTM-[A-Z0-9]{4,12}$/'],
                    'meta_pixel_id' => ['type' => 'text', 'label' => 'Meta Pixel ID', 'default' => '', 'pattern' => '/^[0-9]{6,20}$/'],
                    'google_verification' => ['type' => 'text', 'label' => 'Google Search Console verification code', 'default' => '', 'pattern' => '/^[A-Za-z0-9_\-]{10,100}$/', 'section' => 'Verification', 'help' => 'Only the content value of the meta tag.'],
                    'bing_verification' => ['type' => 'text', 'label' => 'Bing Webmaster Tools verification code', 'default' => '', 'pattern' => '/^[A-Za-z0-9_\-]{10,100}$/'],
                    'newsletter_enabled' => ['type' => 'bool', 'label' => 'Show newsletter signup', 'default' => false, 'section' => 'Newsletter'],
                    'newsletter_action' => ['type' => 'url', 'label' => 'Form action URL', 'default' => '', 'help' => 'The HTTPS form endpoint from your provider (Mailchimp, Brevo, ConvertKit, Buttondown, etc.).', 'https' => true],
                    'newsletter_field' => ['type' => 'text', 'label' => 'Email field name', 'default' => 'email', 'pattern' => '/^[A-Za-z0-9_\-\[\]]{1,60}$/'],
                    'newsletter_hidden' => ['type' => 'textarea', 'label' => 'Hidden fields (name=value per line)', 'default' => '', 'max' => 1000],
                    'newsletter_title' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Get new articles by email', 'max' => 120],
                    'newsletter_text' => ['type' => 'text', 'label' => 'Text', 'default' => 'No spam. Unsubscribe at any time.', 'max' => 240],
                    'newsletter_button' => ['type' => 'text', 'label' => 'Button label', 'default' => 'Subscribe', 'max' => 40],
                    'head_snippet' => ['type' => 'code', 'label' => 'Custom code before </head>', 'default' => '', 'section' => 'Custom code (administrators only)', 'help' => 'Raw HTML output on every public page. Only add code from trusted sources.', 'export' => false],
                    'body_snippet' => ['type' => 'code', 'label' => 'Custom code before </body>', 'default' => '', 'export' => false],
                    'mail_from_email' => ['type' => 'email', 'label' => 'From email address', 'default' => '', 'section' => 'Email delivery', 'export' => false, 'help' => 'Used for password reset emails.'],
                    'mail_from_name' => ['type' => 'text', 'label' => 'From name', 'default' => '', 'max' => 120, 'export' => false],
                    'smtp_host' => ['type' => 'text', 'label' => 'SMTP host', 'default' => '', 'max' => 190, 'export' => false, 'help' => 'Leave empty to use PHP mail().'],
                    'smtp_port' => ['type' => 'int', 'label' => 'SMTP port', 'default' => 587, 'min' => 1, 'max' => 65535, 'export' => false],
                    'smtp_secure' => ['type' => 'select', 'label' => 'SMTP encryption', 'default' => 'tls', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None'], 'export' => false],
                    'smtp_user' => ['type' => 'text', 'label' => 'SMTP username', 'default' => '', 'max' => 190, 'export' => false],
                    'smtp_pass' => ['type' => 'password', 'label' => 'SMTP password', 'default' => '', 'secret' => true],
                ],
            ],
            'security' => [
                'title' => 'Security',
                'fields' => [
                    'login_max_attempts' => ['type' => 'int', 'label' => 'Failed logins before lockout', 'default' => 5, 'min' => 3, 'max' => 20, 'section' => 'Login protection'],
                    'login_lockout_minutes' => ['type' => 'int', 'label' => 'Lockout window (minutes)', 'default' => 15, 'min' => 5, 'max' => 1440],
                    'session_idle_minutes' => ['type' => 'int', 'label' => 'Sign out after inactivity (minutes)', 'default' => 120, 'min' => 10, 'max' => 1440, 'section' => 'Sessions'],
                    'session_max_hours' => ['type' => 'int', 'label' => 'Maximum session length (hours)', 'default' => 12, 'min' => 1, 'max' => 168],
                    'min_password_length' => ['type' => 'int', 'label' => 'Minimum password length', 'default' => 10, 'min' => 8, 'max' => 64, 'section' => 'Passwords'],
                    'authors_can_publish' => ['type' => 'bool', 'label' => 'Authors may publish their own posts', 'default' => false, 'section' => 'Role permissions', 'help' => 'When off, authors submit drafts that an editor or administrator publishes.'],
                    'authors_can_upload' => ['type' => 'bool', 'label' => 'Authors may upload media', 'default' => true],
                ],
            ],
            'theme' => [
                'title' => 'Theme settings',
                'fields' => [
                    'preset' => ['type' => 'select', 'label' => 'Preset', 'default' => 'minimal', 'options' => ThemeService::presetOptions(), 'section' => 'Preset'],
                    'logo' => ['type' => 'media', 'label' => 'Logo', 'default' => null, 'section' => 'Brand assets', 'help' => 'SVG is not accepted; upload a PNG/WebP with a transparent background.'],
                    'logo_dark' => ['type' => 'media', 'label' => 'Logo for dark backgrounds', 'default' => null],
                    'logo_height' => ['type' => 'int', 'label' => 'Logo height (px)', 'default' => 32, 'min' => 16, 'max' => 96],
                    'favicon' => ['type' => 'media', 'label' => 'Favicon', 'default' => null, 'help' => 'Square PNG, at least 48 × 48 px.'],
                    'color_bg' => ['type' => 'color', 'label' => 'Page background', 'default' => '#FAFAF8', 'section' => 'Colours'],
                    'color_surface' => ['type' => 'color', 'label' => 'Surface (cards, header)', 'default' => '#FFFFFF'],
                    'color_text' => ['type' => 'color', 'label' => 'Primary text', 'default' => '#17202A'],
                    'color_muted' => ['type' => 'color', 'label' => 'Muted text', 'default' => '#64748B'],
                    'color_accent' => ['type' => 'color', 'label' => 'Accent', 'default' => '#B5815A'],
                    'color_accent_contrast' => ['type' => 'color', 'label' => 'Text on accent', 'default' => '#17202A', 'help' => 'Button text colour. Keep a contrast ratio of at least 4.5:1 with the accent.'],
                    'color_link' => ['type' => 'color', 'label' => 'Links & accent text', 'default' => '#8A5A36', 'help' => 'Used for links and small accent-coloured text such as category labels.'],
                    'color_border' => ['type' => 'color', 'label' => 'Borders', 'default' => '#E8E8E5'],
                    'color_footer_bg' => ['type' => 'color', 'label' => 'Footer background', 'default' => '#FFFFFF'],
                    'color_footer_text' => ['type' => 'color', 'label' => 'Footer text', 'default' => '#17202A'],
                    'dark_mode' => ['type' => 'select', 'label' => 'Dark appearance', 'default' => 'off', 'options' => ['off' => 'Always light', 'auto' => 'Follow the visitor’s system setting'], 'section' => 'Dark appearance'],
                    'dark_bg' => ['type' => 'color', 'label' => 'Dark background', 'default' => '#111418'],
                    'dark_surface' => ['type' => 'color', 'label' => 'Dark surface', 'default' => '#1A1F26'],
                    'dark_text' => ['type' => 'color', 'label' => 'Dark text', 'default' => '#ECEEF1'],
                    'dark_muted' => ['type' => 'color', 'label' => 'Dark muted text', 'default' => '#A3ACB9'],
                    'dark_border' => ['type' => 'color', 'label' => 'Dark borders', 'default' => '#2B323C'],
                    'radius' => ['type' => 'int', 'label' => 'Corner radius (px)', 'default' => 10, 'min' => 0, 'max' => 28, 'section' => 'Shape & layout'],
                    'card_style' => ['type' => 'select', 'label' => 'Card style', 'default' => 'bordered', 'options' => ['bordered' => 'Bordered', 'shadow' => 'Soft shadow', 'flat' => 'Flat (no frame)']],
                    'button_style' => ['type' => 'select', 'label' => 'Button style', 'default' => 'solid', 'options' => ['solid' => 'Solid', 'outline' => 'Outline', 'pill' => 'Pill']],
                    'container_width' => ['type' => 'int', 'label' => 'Page width (px)', 'default' => 1180, 'min' => 960, 'max' => 1440],
                    'article_width' => ['type' => 'int', 'label' => 'Article reading width (px)', 'default' => 740, 'min' => 600, 'max' => 860],
                    'grid_columns' => ['type' => 'select', 'label' => 'Article grid columns (desktop)', 'default' => '3', 'options' => ['2' => '2 columns', '3' => '3 columns', '4' => '4 columns']],
                    'image_ratio' => ['type' => 'select', 'label' => 'Card image ratio', 'default' => '16:10', 'options' => $ratio],
                    'hero_ratio' => ['type' => 'select', 'label' => 'Article featured image ratio', 'default' => '16:9', 'options' => $ratio + ['original' => 'Original ratio']],
                ],
            ],
            'typography' => [
                'title' => 'Typography',
                'fields' => [
                    'font_heading' => ['type' => 'select', 'label' => 'Heading font', 'default' => 'source-serif-4', 'options' => $fonts, 'section' => 'Fonts', 'help' => 'Fonts are self-hosted; only the selected fonts are downloaded by visitors.'],
                    'font_body' => ['type' => 'select', 'label' => 'Article body font', 'default' => 'source-serif-4', 'options' => $fonts],
                    'font_ui' => ['type' => 'select', 'label' => 'Interface font (navigation, cards, meta)', 'default' => 'inter', 'options' => $fonts],
                    'base_size' => ['type' => 'int', 'label' => 'Base font size (px)', 'default' => 16, 'min' => 14, 'max' => 20, 'section' => 'Sizes'],
                    'article_size' => ['type' => 'int', 'label' => 'Article text size (px)', 'default' => 19, 'min' => 15, 'max' => 24],
                    'line_height' => ['type' => 'select', 'label' => 'Article line height', 'default' => '1.75', 'options' => ['1.5' => '1.5', '1.6' => '1.6', '1.65' => '1.65', '1.7' => '1.7', '1.75' => '1.75', '1.8' => '1.8', '1.9' => '1.9']],
                    'heading_weight' => ['type' => 'select', 'label' => 'Heading weight', 'default' => '600', 'options' => ['400' => 'Regular', '500' => 'Medium', '600' => 'Semibold', '700' => 'Bold', '800' => 'Extra bold']],
                    'heading_scale' => ['type' => 'select', 'label' => 'Heading scale', 'default' => '1.25', 'options' => ['1.2' => 'Compact', '1.25' => 'Balanced', '1.333' => 'Large']],
                    'letter_spacing' => ['type' => 'select', 'label' => 'Heading letter spacing', 'default' => '-0.01em', 'options' => ['0' => 'Normal', '-0.01em' => 'Slightly tight', '-0.02em' => 'Tight']],
                ],
            ],
            'layout' => [
                'title' => 'Header & footer',
                'fields' => [
                    'header_layout' => ['type' => 'select', 'label' => 'Header layout', 'default' => 'split', 'options' => ['split' => 'Logo left, navigation right', 'centered' => 'Centered logo, navigation below', 'stacked' => 'Logo left, navigation below'], 'section' => 'Header'],
                    'header_sticky' => ['type' => 'bool', 'label' => 'Keep header visible while scrolling', 'default' => false],
                    'header_show_name' => ['type' => 'bool', 'label' => 'Show blog name next to the logo', 'default' => true],
                    'header_cta_label' => ['type' => 'text', 'label' => 'Header button label', 'default' => '', 'max' => 40, 'help' => 'Optional, e.g. "Book now" or "Contact us".'],
                    'header_cta_url' => ['type' => 'url', 'label' => 'Header button URL', 'default' => ''],
                    'footer_layout' => ['type' => 'select', 'label' => 'Footer layout', 'default' => 'columns', 'options' => ['columns' => 'Columns', 'simple' => 'Simple single row', 'centered' => 'Centered'], 'section' => 'Footer'],
                    'footer_logo' => ['type' => 'media', 'label' => 'Footer logo', 'default' => null, 'help' => 'Defaults to the main logo.'],
                    'footer_description' => ['type' => 'textarea', 'label' => 'Business description', 'default' => '', 'max' => 500],
                    'footer_address' => ['type' => 'textarea', 'label' => 'Address', 'default' => '', 'max' => 300],
                    'footer_phone' => ['type' => 'text', 'label' => 'Phone number', 'default' => '', 'max' => 40, 'pattern' => '/^[0-9+()\-.\s]{4,40}$/'],
                    'footer_email' => ['type' => 'email', 'label' => 'Email', 'default' => ''],
                    'footer_show_social' => ['type' => 'bool', 'label' => 'Show social links', 'default' => true],
                    'footer_copyright' => ['type' => 'text', 'label' => 'Copyright line', 'default' => '© {year} {site_name}. All rights reserved.', 'max' => 200, 'help' => 'Variables: {year}, {site_name}.'],
                    'footer_privacy_url' => ['type' => 'url', 'label' => 'Privacy policy URL', 'default' => ''],
                    'footer_terms_url' => ['type' => 'url', 'label' => 'Terms URL', 'default' => ''],
                    'footer_main_link_label' => ['type' => 'text', 'label' => 'Main website link label', 'default' => 'Visit our website', 'max' => 60, 'help' => 'Links to the main website URL from General settings.'],
                ],
            ],
        ];
    }
}
