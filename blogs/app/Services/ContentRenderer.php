<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns sanitised post HTML into the final article markup:
 * heading anchors + table of contents, responsive <picture> images from the media
 * library, safe external link attributes, responsive tables and embeds.
 */
final class ContentRenderer
{
    public function __construct(private App $app)
    {
    }

    /**
     * @return array{html: string, toc: array<int, array{id: string, text: string, level: int}>}
     */
    public function render(string $html, array $options = []): array
    {
        if (trim($html) === '') {
            return ['html' => '', 'toc' => []];
        }
        [$doc, $root] = self::parse($html);
        $xp = new DOMXPath($doc);
        $toc = $this->headings($xp);
        $this->images($doc, $xp, $options);
        $this->links($xp);
        $this->tables($doc, $xp);
        $this->embeds($doc, $xp);
        return ['html' => self::innerHtml($root), 'toc' => $toc];
    }

    /** @return array{0: DOMDocument, 1: DOMElement} */
    public static function parse(string $html): array
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="bf-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('bf-root');
        if (!$root) {
            $root = $doc->createElement('div');
        }
        return [$doc, $root];
    }

    public static function innerHtml(DOMElement $el): string
    {
        $out = '';
        foreach ($el->childNodes as $child) {
            $out .= $el->ownerDocument->saveHTML($child);
        }
        return $out;
    }

    private function headings(DOMXPath $xp): array
    {
        $toc = [];
        $used = [];
        foreach ($xp->query('//h2|//h3|//h4') as $h) {
            /** @var DOMElement $h */
            $text = trim(preg_replace('/\s+/u', ' ', $h->textContent) ?? '');
            if ($text === '') {
                continue;
            }
            $id = $h->getAttribute('id');
            if ($id === '' || isset($used[$id]) || !preg_match('/^[a-z][a-z0-9\-]*$/', $id)) {
                $id = \BlogFlow\Validation\Validator::slugify($text, 60) ?: 'section';
                if (!preg_match('/^[a-z]/', $id)) {
                    $id = 's-' . $id;
                }
            }
            $baseId = $id;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = $baseId . '-' . $i;
            }
            $used[$id] = true;
            $h->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) substr($h->nodeName, 1)];
        }
        return $toc;
    }

    private function images(DOMDocument $doc, DOMXPath $xp, array $options): void
    {
        $nodes = iterator_to_array($xp->query('//img'));
        $ids = [];
        foreach ($nodes as $img) {
            $ids[] = (int) $img->getAttribute('data-media-id');
        }
        $media = $this->app->service(MediaService::class);
        $rows = $media->findMany($ids);
        $article = max(600, $this->app->settings->int('theme.article_width'));

        foreach ($nodes as $index => $img) {
            /** @var DOMElement $img */
            $figure = $img->parentNode instanceof DOMElement && $img->parentNode->nodeName === 'figure' ? $img->parentNode : null;
            $width = $figure ? (int) ($figure->getAttribute('data-width') ?: 100) : 100;
            $align = $figure ? ($figure->getAttribute('data-align') ?: 'center') : 'center';
            if ($figure) {
                $figure->setAttribute('class', trim('bf-figure bf-align-' . $align . ' bf-w-' . $width));
                $figure->removeAttribute('data-width');
                $figure->removeAttribute('data-align');
            }
            $id = (int) $img->getAttribute('data-media-id');
            $row = $rows[$id] ?? null;
            $alt = $img->getAttribute('alt');
            if ($row) {
                $px = $align === 'wide' ? (int) round($article * 1.35) : (int) round($article * $width / 100);
                $sizes = sprintf('(max-width: %dpx) 100vw, %dpx', $px + 40, $px);
                $html = $media->picture($row, [
                    'alt' => $alt !== '' ? $alt : (string) ($row['alt_text'] ?? ''),
                    'sizes' => $sizes,
                    'loading' => 'lazy',
                    'fallback' => $width <= 50 ? 'card' : 'medium',
                ]);
                $fragment = $doc->createDocumentFragment();
                [$tmpDoc, $tmpRoot] = self::parse($html);
                foreach ($tmpRoot->childNodes as $child) {
                    $fragment->appendChild($doc->importNode($child, true));
                }
                $img->parentNode->replaceChild($fragment, $img);
            } else {
                $img->removeAttribute('data-media-id');
                $img->setAttribute('loading', 'lazy');
                $img->setAttribute('decoding', 'async');
            }
        }
    }

    private function links(DOMXPath $xp): void
    {
        $url = $this->app->url;
        foreach ($xp->query('//a[@href]') as $a) {
            /** @var DOMElement $a */
            $href = $a->getAttribute('href');
            $rel = array_filter(explode(' ', strtolower($a->getAttribute('rel'))));
            if ($a->getAttribute('target') === '_blank') {
                $rel[] = 'noopener';
                if ($url->isExternal($href)) {
                    $rel[] = 'noreferrer';
                }
            }
            if ($rel) {
                $a->setAttribute('rel', implode(' ', array_unique($rel)));
            }
        }
    }

    private function tables(DOMDocument $doc, DOMXPath $xp): void
    {
        foreach (iterator_to_array($xp->query('//table')) as $i => $table) {
            /** @var DOMElement $table */
            if ($table->parentNode instanceof DOMElement && str_contains($table->parentNode->getAttribute('class'), 'bf-table')) {
                continue;
            }
            // Promote the first row to a header row when it only contains <th>.
            $wrap = $doc->createElement('div');
            $wrap->setAttribute('class', 'bf-table');
            $wrap->setAttribute('role', 'region');
            $wrap->setAttribute('tabindex', '0');
            $wrap->setAttribute('aria-label', 'Table ' . ($i + 1));
            $table->parentNode->replaceChild($wrap, $table);
            $wrap->appendChild($table);
        }
    }

    private function embeds(DOMDocument $doc, DOMXPath $xp): void
    {
        foreach (iterator_to_array($xp->query('//iframe')) as $iframe) {
            /** @var DOMElement $iframe */
            $src = $iframe->getAttribute('src');
            if ($src === '') {
                $iframe->parentNode?->removeChild($iframe);
                continue;
            }
            $iframe->setAttribute('loading', 'lazy');
            $iframe->setAttribute('title', $iframe->getAttribute('title') ?: 'Embedded video');
            $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            $iframe->setAttribute('allow', 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen');
            $iframe->removeAttribute('width');
            $iframe->removeAttribute('height');
            $parent = $iframe->parentNode;
            if ($parent instanceof DOMElement && $parent->nodeName === 'div') {
                $parent->setAttribute('class', 'bf-embed');
                $parent->removeAttribute('data-youtube-video');
            } else {
                $wrap = $doc->createElement('div');
                $wrap->setAttribute('class', 'bf-embed');
                $parent->replaceChild($wrap, $iframe);
                $wrap->appendChild($iframe);
            }
        }
    }
}
