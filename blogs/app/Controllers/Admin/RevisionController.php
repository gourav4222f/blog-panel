<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Services\HtmlSanitizer;
use BlogFlow\Services\PostService;

final class RevisionController extends AdminController
{
    private function post(string $id): array
    {
        $post = $this->db->one('SELECT * FROM {{posts}} WHERE id = ?', [$this->id($id)]) ?? throw new HttpException(404);
        $this->authorize($this->app->auth->canEditPost($post));
        return $post;
    }

    private function revision(array $post, string $rid): array
    {
        $rev = $this->db->one('SELECT r.*, u.name AS user_name FROM {{post_revisions}} r LEFT JOIN {{users}} u ON u.id = r.user_id WHERE r.id = ? AND r.post_id = ?', [$this->id($rid), $post['id']]);
        if (!$rev || ($rev['kind'] === 'autosave' && (int) $rev['user_id'] !== $this->app->auth->id())) {
            throw new HttpException(404);
        }
        return $rev;
    }

    public function index(string $id): Response
    {
        $post = $this->post($id);
        $revisions = $this->db->all(
            'SELECT r.id, r.kind, r.title, r.note, r.created_at, r.content_html, u.name AS user_name FROM {{post_revisions}} r
             LEFT JOIN {{users}} u ON u.id = r.user_id WHERE r.post_id = ? AND (r.kind = \'revision\' OR r.user_id = ?) ORDER BY r.id DESC',
            [$post['id'], $this->app->auth->id()]
        );
        foreach ($revisions as &$r) {
            $r['words'] = HtmlSanitizer::wordCount(HtmlSanitizer::text((string) $r['content_html']));
            unset($r['content_html']);
        }
        return $this->page('posts/revisions', ['pageTitle' => 'Revisions', 'post' => $post, 'revisions' => $revisions]);
    }

    public function show(string $id, string $rid): Response
    {
        $post = $this->post($id);
        $rev = $this->revision($post, $rid);
        $currentLines = self::lines((string) $post['content_html']);
        $revLines = self::lines((string) $rev['content_html']);
        return $this->page('posts/revision', [
            'pageTitle' => 'Revision',
            'post' => $post,
            'rev' => $rev,
            'diff' => self::diff($currentLines, $revLines),
        ]);
    }

    public function restore(string $id, string $rid): Response
    {
        $post = $this->post($id);
        $rev = $this->revision($post, $rid);
        try {
            $this->app->service(PostService::class)->restoreRevision($post, $rev);
        } catch (\RuntimeException $e) {
            $this->flash('error', 'The revision could not be restored: ' . $e->getMessage());
            return $this->redirect($this->app->url->admin('posts/' . $post['id'] . '/revisions'));
        }
        if ($rev['kind'] === 'autosave') {
            $this->db->delete('post_revisions', 'id = ?', [$rev['id']]);
        }
        $this->log('post_revision_restore', 'post', (int) $post['id'], 'Restored a ' . $rev['kind'] . ' of “' . $post['title'] . '”');
        $this->flash('success', 'Revision restored. The previous version is kept in the revision history.');
        return $this->redirect($this->app->url->admin('posts/' . $post['id'] . '/edit'));
    }

    /** Splits HTML into comparable text lines (one per block element). */
    private static function lines(string $html): array
    {
        $text = HtmlSanitizer::text($html);
        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
    }

    /**
     * Line diff via longest common subsequence.
     *
     * @return array<int, array{0: string, 1: string}> [op (=, -, +), line]
     */
    public static function diff(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        if ($n * $m > 4_000_000) {
            return array_merge(array_map(fn ($l) => ['-', $l], $a), array_map(fn ($l) => ['+', $l], $b));
        }
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $out = [];
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['=', $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['-', $a[$i++]];
            } else {
                $out[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $out[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $out[] = ['+', $b[$j++]];
        }
        return $out;
    }
}
