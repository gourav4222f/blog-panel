<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Models\Posts;

/**
 * Finds links in post content and verifies them. Internal links are resolved against the
 * database; external links are checked over HTTP in small batches, never to private networks.
 */
final class LinkChecker
{
    public function __construct(private App $app)
    {
    }

    /** @return array<int, array{post_id: int, title: string, href: string, internal: bool}> */
    public function links(): array
    {
        $out = [];
        $rows = $this->app->db->all('SELECT p.id, p.title, p.content_html FROM {{posts}} p WHERE ' . Posts::PUBLIC_WHERE, [Posts::now()]);
        foreach ($rows as $r) {
            if (!preg_match_all('/<a\s[^>]*href="([^"]+)"/i', (string) $r['content_html'], $m)) {
                continue;
            }
            foreach (array_unique($m[1]) as $href) {
                $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
                if (str_starts_with($href, '#') || preg_match('/^(mailto|tel):/i', $href)) {
                    continue;
                }
                $out[] = ['post_id' => (int) $r['id'], 'title' => $r['title'], 'href' => $href, 'internal' => $this->app->url->relativePath($href) !== null];
            }
        }
        return $out;
    }

    public static function isPublicHost(string $host): bool
    {
        return self::publicIp($host) !== null;
    }

    /** Resolves a host and returns one of its IPs only if every address is public. */
    public static function publicIp(string $host): ?string
    {
        $host = trim($host, '[]');
        if ($host === '' || strtolower($host) === 'localhost' || str_ends_with(strtolower($host), '.local') || str_ends_with(strtolower($host), '.internal')) {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'),
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
        );
        if (!$ips) {
            $resolved = gethostbyname($host);
            $ips = $resolved !== $host ? [$resolved] : [];
        }
        if (!$ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }
        return (string) $ips[0];
    }

    /** @return array{status: ?int, error: ?string} */
    public function check(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => null, 'error' => 'cURL is not available on this server'];
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ip = preg_match('#^https?://#i', $url) ? self::publicIp($host) : null;
        if ($ip === null) {
            return ['status' => null, 'error' => 'Skipped (not a public host)'];
        }
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: (str_starts_with(strtolower($url), 'https') ? 443 : 80));
        // Pin the validated address so a second DNS lookup cannot point cURL at a private network.
        $pin = [trim($host, '[]') . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => $pin,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'BlogFlowLinkChecker/1.0 (+' . $this->app->url->home() . ')',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (in_array($code, [403, 405, 501], true)) {
            // Some servers reject HEAD; retry with a tiny GET.
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RESOLVE => $pin, CURLOPT_RANGE => '0-0', CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BlogFlowLinkChecker/1.0)']);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: $code;
            curl_close($ch);
        }
        return ['status' => $code ?: null, 'error' => $code ? null : ($err ?: 'No response')];
    }

    /** Checks up to $limit external links not checked in the last 7 days. */
    public function checkBatch(int $limit = 5): array
    {
        $external = array_values(array_unique(array_map(fn ($l) => $l['href'], array_filter($this->links(), fn ($l) => !$l['internal'] && preg_match('#^https?://#i', $l['href'])))));
        $fresh = [];
        foreach ($this->app->db->all('SELECT url_hash FROM {{link_checks}} WHERE checked_at > ?', [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]) as $r) {
            $fresh[$r['url_hash']] = true;
        }
        $todo = array_values(array_filter($external, fn ($u) => !isset($fresh[sha1($u)])));
        $log = [];
        foreach (array_slice($todo, 0, $limit) as $url) {
            $r = $this->check($url);
            $this->app->db->query(
                'INSERT INTO {{link_checks}} (url_hash, url, status_code, error, checked_at) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status_code = VALUES(status_code), error = VALUES(error), checked_at = VALUES(checked_at)',
                [sha1($url), mb_substr($url, 0, 1000), $r['status'], $r['error'] ? mb_substr($r['error'], 0, 255) : null, gmdate('Y-m-d H:i:s')]
            );
            $log[] = ($r['status'] ? $r['status'] : '—') . ' ' . $url;
        }
        $remaining = max(0, count($todo) - $limit);
        return ['total' => count($external), 'done' => count($external) - $remaining, 'remaining' => $remaining, 'log' => $log];
    }
}
