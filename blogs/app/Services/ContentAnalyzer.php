<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Models\Posts;
use DOMElement;
use DOMXPath;

/**
 * Rule-based, advisory on-page SEO and content checks for a post.
 * Length figures are guidance for how snippets display, never hard rules.
 */
final class ContentAnalyzer
{
    public const CRITICAL = 'critical';
    public const WARNING = 'warning';
    public const SUGGESTION = 'suggestion';
    public const PASSED = 'passed';

    private array $checks = [];

    public function __construct(private App $app)
    {
    }

    private function add(string $key, string $severity, string $message): void
    {
        $this->checks[] = ['key' => $key, 'severity' => $severity, 'message' => $message];
    }

    /**
     * @param array $post post fields (title, slug, excerpt, content_html, seo_title, meta_description, focus_keyword,
     *                    featured_image_id, featured_image_alt, robots_index, canonical_url, id, category_name, author_name)
     * @return array{checks: array, score: int, stats: array, serp: array}
     */
    public function analyze(array $post): array
    {
        $this->checks = [];
        $seo = $this->app->service(SeoService::class);
        $html = (string) ($post['content_html'] ?? '');
        $text = HtmlSanitizer::text($html);
        $words = HtmlSanitizer::wordCount($text);
        $title = trim((string) ($post['title'] ?? ''));
        $fullTitle = $title !== '' ? $seo->postTitle($post) : '';
        $description = $seo->postDescription($post + ['content_text' => $text]);
        $keyword = mb_strtolower(trim((string) ($post['focus_keyword'] ?? '')));
        $slug = (string) ($post['slug'] ?? '');

        [$doc] = $html !== '' ? ContentRenderer::parse($html) : [null];
        $xp = $doc ? new DOMXPath($doc) : null;

        // ----- Title -----
        if ($title === '') {
            $this->add('title_missing', self::CRITICAL, 'Add a title. It becomes the page’s only H1 heading.');
        } else {
            $len = mb_strlen($fullTitle);
            if ($len > 65) {
                $this->add('title_length', self::SUGGESTION, sprintf('The search title is %d characters. Titles longer than about 60 characters are often shortened in results.', $len));
            } elseif ($len < 25) {
                $this->add('title_length', self::SUGGESTION, sprintf('The search title is only %d characters. A more descriptive title can help searchers.', $len));
            } else {
                $this->add('title_length', self::PASSED, sprintf('Search title length looks good (%d characters).', $len));
            }
            $this->add('h1', self::PASSED, 'The article title is output as the single H1; H1s inside the content are converted to H2.');
        }

        // ----- Meta description -----
        $customDesc = trim((string) ($post['meta_description'] ?? ''));
        if ($customDesc === '' && trim((string) ($post['excerpt'] ?? '')) === '') {
            $this->add('description_missing', self::WARNING, 'No meta description or excerpt. A fallback is generated from the content, but a written summary usually performs better.');
        } else {
            $dl = mb_strlen($description);
            if ($dl > 165) {
                $this->add('description_length', self::SUGGESTION, sprintf('The description is %d characters; around 150–160 is usually shown in full.', $dl));
            } elseif ($dl < 70) {
                $this->add('description_length', self::SUGGESTION, sprintf('The description is short (%d characters). Summarise what the reader will learn.', $dl));
            } else {
                $this->add('description_length', self::PASSED, 'Meta description length looks good.');
            }
        }

        // ----- Excerpt -----
        $excerpt = trim((string) ($post['excerpt'] ?? ''));
        if ($excerpt === '') {
            $this->add('excerpt', self::SUGGESTION, 'Add a short excerpt. It appears on article cards and under the title.');
        } elseif (mb_strlen($excerpt) < 50) {
            $this->add('excerpt', self::SUGGESTION, 'The excerpt is very short. One or two sentences works well.');
        } else {
            $this->add('excerpt', self::PASSED, 'Excerpt present.');
        }

        // ----- Slug -----
        if ($slug !== '' && strlen($slug) > 75) {
            $this->add('slug_length', self::SUGGESTION, 'The URL slug is long. Short, descriptive slugs are easier to read and share.');
        } elseif ($slug !== '') {
            $this->add('slug_length', self::PASSED, 'URL slug is concise.');
        }

        // ----- Content length & readability -----
        if ($words === 0) {
            $this->add('content_empty', self::CRITICAL, 'The article has no content.');
        } elseif ($words < 300) {
            $this->add('thin_content', self::WARNING, sprintf('Only %d words. Short articles can be fine, but make sure the topic is fully answered.', $words));
        } else {
            $this->add('thin_content', self::PASSED, sprintf('%d words.', $words));
        }
        if ($words > 0) {
            $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $avg = $sentences ? $words / count($sentences) : $words;
            if ($avg > 25) {
                $this->add('readability_sentences', self::SUGGESTION, sprintf('Sentences average %.0f words. Shorter sentences are easier to read on mobile.', $avg));
            } else {
                $this->add('readability_sentences', self::PASSED, 'Sentence length is comfortable.');
            }
        }

        // ----- Headings -----
        $headings = [];
        if ($xp) {
            foreach ($xp->query('//h2|//h3|//h4') as $h) {
                $headings[] = ['level' => (int) substr($h->nodeName, 1), 'text' => trim($h->textContent)];
            }
            $long = 0;
            foreach ($xp->query('//p') as $p) {
                if (HtmlSanitizer::wordCount($p->textContent) > 150) {
                    $long++;
                }
            }
            if ($long > 0) {
                $this->add('readability_paragraphs', self::SUGGESTION, sprintf('%d paragraph(s) exceed 150 words. Break long paragraphs up.', $long));
            }
        }
        if ($words >= 300 && !$headings) {
            $this->add('headings_missing', self::WARNING, 'No subheadings. Use H2 headings to structure longer articles.');
        } elseif ($headings) {
            $prev = 1;
            $skips = 0;
            foreach ($headings as $h) {
                if ($h['level'] > $prev + 1) {
                    $skips++;
                }
                $prev = $h['level'];
            }
            if ($headings[0]['level'] !== 2) {
                $skips++;
            }
            if ($skips) {
                $this->add('heading_hierarchy', self::WARNING, 'Heading levels skip (e.g. H2 → H4, or the first heading is not H2). Keep a logical order.');
            } else {
                $this->add('heading_hierarchy', self::PASSED, sprintf('Heading hierarchy is valid (%d headings).', count($headings)));
            }
            if (count(array_filter($headings, fn ($h) => $h['text'] === '')) > 0) {
                $this->add('heading_empty', self::WARNING, 'There is an empty heading.');
            }
        }

        // ----- Intro paragraph -----
        $firstParagraph = '';
        if ($xp && ($p = $xp->query('//p')->item(0))) {
            $firstParagraph = trim($p->textContent);
            if (HtmlSanitizer::wordCount($firstParagraph) > 90) {
                $this->add('intro_length', self::SUGGESTION, 'The opening paragraph is long. A concise introduction helps readers decide to continue.');
            }
        }

        // ----- Focus keyword (editorial guidance, no stuffing) -----
        if ($keyword === '') {
            $this->add('keyword_missing', self::SUGGESTION, 'Set a focus keyword to get keyword placement guidance (optional).');
        } else {
            $kw = fn (string $haystack) => $haystack !== '' && mb_stripos($haystack, $keyword) !== false;
            $kwSlug = \BlogFlow\Validation\Validator::slugify($keyword);
            $this->add('keyword_title', $kw($fullTitle) || $kw($title) ? self::PASSED : self::SUGGESTION,
                $kw($fullTitle) || $kw($title) ? 'Focus keyword appears in the title.' : 'Consider using the focus keyword (or a natural variation) in the title.');
            $this->add('keyword_description', $kw($description) ? self::PASSED : self::SUGGESTION,
                $kw($description) ? 'Focus keyword appears in the description.' : 'The description does not mention the focus keyword.');
            $this->add('keyword_intro', $kw($firstParagraph) ? self::PASSED : self::SUGGESTION,
                $kw($firstParagraph) ? 'Focus keyword appears in the introduction.' : 'Mention the topic early, in the first paragraph.');
            $this->add('keyword_slug', $kwSlug !== '' && str_contains($slug, $kwSlug) ? self::PASSED : self::SUGGESTION,
                $kwSlug !== '' && str_contains($slug, $kwSlug) ? 'Focus keyword appears in the URL.' : 'The URL slug does not contain the focus keyword.');
            if ($words > 0) {
                $count = mb_substr_count(mb_strtolower($text), $keyword);
                $kwWords = max(1, HtmlSanitizer::wordCount($keyword));
                $density = $count * $kwWords / $words * 100;
                if ($density > 3) {
                    $this->add('keyword_density', self::WARNING, sprintf('The focus keyword makes up %.1f%% of the text. Repetition can read as keyword stuffing; write naturally.', $density));
                } elseif ($count === 0) {
                    $this->add('keyword_density', self::SUGGESTION, 'The focus keyword does not appear in the body text.');
                } else {
                    $this->add('keyword_density', self::PASSED, sprintf('Focus keyword used %d time(s) — natural usage.', $count));
                }
            }
            if ($headings) {
                $inHeading = (bool) array_filter($headings, fn ($h) => $kw($h['text']));
                $this->add('keyword_headings', $inHeading ? self::PASSED : self::SUGGESTION,
                    $inHeading ? 'A subheading covers the focus topic.' : 'No subheading mentions the focus topic.');
            }
            $duplicate = $this->app->db->value(
                'SELECT title FROM {{posts}} WHERE deleted_at IS NULL AND LOWER(focus_keyword) = ? AND id <> ? LIMIT 1',
                [$keyword, (int) ($post['id'] ?? 0)]
            );
            if ($duplicate) {
                $this->add('keyword_duplicate', self::SUGGESTION, sprintf('“%s” already targets the same focus keyword. Consider whether both articles compete for the same search.', $duplicate));
            }
        }

        // ----- Images -----
        $imgCount = 0;
        $missingAlt = 0;
        if ($xp) {
            foreach ($xp->query('//img') as $img) {
                /** @var DOMElement $img */
                $imgCount++;
                if (trim($img->getAttribute('alt')) === '') {
                    $missingAlt++;
                }
            }
        }
        if ($missingAlt > 0) {
            $this->add('image_alt', self::WARNING, sprintf('%d of %d content image(s) have no ALT text. Describe each informative image for screen readers and image search.', $missingAlt, $imgCount));
        } elseif ($imgCount > 0) {
            $this->add('image_alt', self::PASSED, 'All content images have ALT text.');
        }
        if (empty($post['featured_image_id'])) {
            $this->add('featured_image', self::WARNING, 'No featured image. It is used for cards, social sharing and article structured data.');
        } else {
            $alt = trim((string) ($post['featured_image_alt'] ?? ''));
            if ($alt === '') {
                $media = $this->app->service(MediaService::class)->find((int) $post['featured_image_id']);
                $alt = trim((string) ($media['alt_text'] ?? ''));
            }
            $this->add('featured_image', $alt === '' ? self::WARNING : self::PASSED, $alt === '' ? 'The featured image has no ALT text.' : 'Featured image with ALT text.');
        }

        // ----- Links -----
        $internal = 0;
        $external = 0;
        $broken = [];
        $redirected = [];
        if ($xp) {
            foreach ($xp->query('//a[@href]') as $a) {
                /** @var DOMElement $a */
                $href = trim($a->getAttribute('href'));
                if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel):/i', $href)) {
                    continue;
                }
                $rel = $this->app->url->relativePath($href);
                if ($rel !== null) {
                    $internal++;
                    $status = $this->checkInternalPath($rel);
                    if ($status === 'missing') {
                        $broken[] = $href;
                    } elseif ($status === 'redirect') {
                        $redirected[] = $href;
                    }
                } elseif (preg_match('#^https?://#i', $href)) {
                    if ($this->app->url->isExternal($href)) {
                        $external++;
                    } else {
                        $internal++;
                    }
                }
            }
        }
        if ($words >= 150) {
            $this->add('internal_links', $internal > 0 ? self::PASSED : self::SUGGESTION,
                $internal > 0 ? sprintf('%d internal link(s).', $internal) : 'No internal links. Link to related articles or pages to help readers and crawlers.');
        }
        if ($external > 0) {
            $this->add('external_links', self::PASSED, sprintf('%d external link(s). Links opening in a new tab get rel="noopener" automatically.', $external));
        }
        if ($broken) {
            $this->add('broken_internal', self::WARNING, sprintf('%d internal link(s) point to pages that do not exist or are not published: %s', count($broken), implode(', ', array_slice($broken, 0, 3))));
        }
        if ($redirected) {
            $this->add('redirected_internal', self::SUGGESTION, sprintf('%d internal link(s) go through a redirect. Update them to the final URL: %s', count($redirected), implode(', ', array_slice($redirected, 0, 3))));
        }

        // ----- Indexing & canonical -----
        if (($post['robots_index'] ?? 'default') === 'noindex' || (($post['robots_index'] ?? 'default') === 'default' && !$this->app->settings->bool('seo.robots_posts_index'))) {
            $this->add('noindex', self::WARNING, 'This post is set to noindex and will be excluded from search results and the sitemap.');
        } else {
            $this->add('noindex', self::PASSED, 'The post can be indexed.');
        }
        $canonical = trim((string) ($post['canonical_url'] ?? ''));
        if ($canonical !== '' && $slug !== '' && rtrim($canonical, '/') !== rtrim($this->app->url->post($slug), '/')) {
            $this->add('canonical', self::WARNING, 'The canonical URL points to a different page, so this post is left out of the sitemap. Only use this for syndicated content.');
        }

        // ----- Duplicates -----
        if ($title !== '') {
            $dupTitle = $this->app->db->value(
                'SELECT title FROM {{posts}} WHERE deleted_at IS NULL AND id <> ? AND (title = ? OR (seo_title <> \'\' AND seo_title = ?)) LIMIT 1',
                [(int) ($post['id'] ?? 0), $title, (string) ($post['seo_title'] ?? '')]
            );
            if ($dupTitle) {
                $this->add('duplicate_title', self::WARNING, sprintf('Another post uses the same title (“%s”). Unique titles help search engines tell pages apart.', $dupTitle));
            }
        }
        if ($customDesc !== '') {
            $dupDesc = $this->app->db->value('SELECT title FROM {{posts}} WHERE deleted_at IS NULL AND id <> ? AND meta_description = ? LIMIT 1', [(int) ($post['id'] ?? 0), $customDesc]);
            if ($dupDesc) {
                $this->add('duplicate_description', self::WARNING, sprintf('“%s” has the same meta description.', $dupDesc));
            }
        }

        $weights = [self::CRITICAL => 0, self::WARNING => 0.4, self::SUGGESTION => 0.8, self::PASSED => 1];
        $scored = array_filter($this->checks, fn ($c) => $c['key'] !== 'h1');
        $score = $scored ? (int) round(array_sum(array_map(fn ($c) => $weights[$c['severity']], $scored)) / count($scored) * 100) : 0;
        if (array_filter($this->checks, fn ($c) => $c['severity'] === self::CRITICAL)) {
            $score = min($score, 40);
        }

        $order = [self::CRITICAL => 0, self::WARNING => 1, self::SUGGESTION => 2, self::PASSED => 3];
        usort($this->checks, fn ($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return [
            'checks' => $this->checks,
            'score' => $score,
            'stats' => [
                'words' => $words,
                'reading_time' => HtmlSanitizer::readingTime($words),
                'headings' => count($headings),
                'images' => $imgCount,
                'internal_links' => $internal,
                'external_links' => $external,
            ],
            'serp' => [
                'title' => $fullTitle,
                'description' => $description,
                'url' => $slug !== '' ? $this->app->url->post($slug) : $this->app->url->home(),
            ],
        ];
    }

    /** @return 'ok'|'missing'|'redirect'|'external' */
    public function checkInternalPath(string $path): string
    {
        $url = $this->app->url;
        $path = RedirectService::normalize($path);
        if ($path === '/' || preg_match('#^/(page/\d+|search|feed\.xml|sitemap\.xml)$#', $path)) {
            return 'ok';
        }
        if (preg_match('#^/(assets|uploads)/#', $path)) {
            return is_file(BF_ROOT . rawurldecode($path)) ? 'ok' : 'missing';
        }
        $redirects = $this->app->db->value('SELECT 1 FROM {{redirects}} WHERE source_hash = ? AND is_enabled = 1', [sha1($path)]);
        if ($redirects) {
            return 'redirect';
        }
        $parts = explode('/', trim($path, '/'));
        $db = $this->app->db;
        $bases = [$url->categoryBase() => 'categories', $url->tagBase() => 'tags', $url->authorBase() => 'authors'];
        if (count($parts) >= 2 && isset($bases[$parts[0]])) {
            return $db->value('SELECT 1 FROM {{' . $bases[$parts[0]] . '}} WHERE slug = ?', [$parts[1]]) ? 'ok' : 'missing';
        }
        if (count($parts) === 1) {
            return $db->value('SELECT 1 FROM {{posts}} p WHERE p.slug = ? AND ' . Posts::PUBLIC_WHERE, [$parts[0], Posts::now()]) ? 'ok' : 'missing';
        }
        return 'missing';
    }
}
