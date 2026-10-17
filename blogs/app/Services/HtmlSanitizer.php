<?php

declare(strict_types=1);

namespace BlogFlow\Services;

/**
 * Allowlist-based HTML sanitisation for post content using HTML Purifier.
 * Client-side editor output is never trusted.
 */
final class HtmlSanitizer
{
    private static ?\HTMLPurifier $purifier = null;

    private static function purifier(): \HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }
        $config = \HTMLPurifier_Config::createDefault();
        $cacheDir = BF_ROOT . '/storage/cache/purifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'mark', 'code[class]', 'pre[class]',
            'blockquote', 'ul', 'ol[start]', 'li', 'h2[id|style]', 'h3[id|style]', 'h4[id|style]',
            'a[href|title|target|rel]', 'img[src|alt|width|height|data-media-id|title]',
            'figure[class|data-align|data-width]', 'figcaption',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th[colspan|rowspan|scope|style]', 'td[colspan|rowspan|style]', 'caption',
            'hr', 'span[class]', 'div[class|data-youtube-video|data-embed]', 'iframe[src|width|height|allowfullscreen|frameborder|title]',
        ]));
        $config->set('CSS.AllowedProperties', ['text-align']);
        $config->set('Attr.EnableID', true);
        $config->set('Attr.DefaultImageAlt', '');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.youtube-nocookie\.com/embed/|www\.youtube\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', false);
        $config->set('Output.Newline', "\n");
        $config->set('HTML.DefinitionID', 'blogflow-content');
        $config->set('HTML.DefinitionRev', 3);
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
            $def->addAttribute('img', 'data-media-id', 'Number');
            $def->addAttribute('figure', 'data-align', 'Enum#left,center,right,wide');
            $def->addAttribute('figure', 'data-width', 'Enum#25,33,50,66,75,100');
            $def->addAttribute('div', 'data-youtube-video', 'Text');
            $def->addAttribute('div', 'data-embed', 'Enum#youtube,vimeo');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool#allowfullscreen');
            $def->addAttribute('th', 'scope', 'Enum#col,row,colgroup,rowgroup');
        }
        return self::$purifier = new \HTMLPurifier($config);
    }

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        // The article title is the page's only H1; demote content H1s and collapse deep headings.
        $html = (string) preg_replace(['#<(/?)h1(\s|>)#i', '#<(/?)h[56](\s|>)#i'], ['<$1h2$2', '<$1h4$2'], $html);
        $clean = self::purifier()->purify($html);
        // Remove empty paragraphs the editor may produce.
        return trim((string) preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $clean));
    }

    /** Plain text for search, excerpts and analysis. */
    public static function text(string $html): string
    {
        $html = (string) preg_replace('#<(br|/p|/h[1-6]|/li|/blockquote|/tr|/figcaption|/pre)[^>]*>#i', "$0\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = (string) preg_replace("/\n\s*\n+/", "\n\n", $text);
        return trim($text);
    }

    public static function wordCount(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }
        return count(preg_split('/[\s]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    public static function readingTime(int $words): int
    {
        return $words === 0 ? 0 : max(1, (int) ceil($words / 225));
    }

    /** Short plain-text summary limited to roughly $length characters at a word boundary. */
    public static function summarize(string $text, int $length = 160): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $length * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " ,.;:-–—") . '…';
    }
}
