<?php
/** @var ?array $post */
/** @var array $input */
/** @var array $errors */
$this->layout('admin/layout');
$isNew = $post === null;
$status = $post['status'] ?? 'draft';
$v = fn (string $k, $d = '') => $input[$k] ?? $d;
$err = fn (string $k) => isset($errors[$k]) ? '<p class="error-text">' . e($errors[$k]) . '</p>' : '';
$inv = fn (string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$action = $isNew ? bf_admin_url('posts') : bf_admin_url('posts/' . $post['id']);
$badge = ['published' => 'badge-green', 'draft' => 'badge-gray', 'scheduled' => 'badge-blue'];
$url = bf_url();
$checkColors = ['critical' => 'bg-red-500', 'warning' => 'bg-amber-500', 'suggestion' => 'bg-sky-500', 'passed' => 'bg-emerald-500'];
$editorData = [
    'html' => (string) $v('content_html'),
    'uploadUrl' => bf_admin_url('media/upload'),
    'canUpload' => bf_can('media.upload'),
    'postsApi' => bf_admin_url('api/posts'),
    'blogBase' => $url->base(),
    'maxUpload' => $mediaCaps['max'],
];
?>
<?php $this->start('head'); ?>
<script type="application/json" id="editor-data"><?= json_encode($editorData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php $this->stop(); ?>

<form method="post" action="<?= e($action) ?>" id="post-form" class="bf-post-form"
      data-autosave-url="<?= e(bf_admin_url('posts/autosave')) ?>"
      data-analyze-url="<?= e(bf_admin_url('api/seo-analyze')) ?>"
      data-slug-url="<?= e(bf_admin_url('api/slug')) ?>"
      data-post-id="<?= (int) ($post['id'] ?? 0) ?>"
      data-original-slug="<?= e($post['slug'] ?? '') ?>"
      data-status="<?= e($status) ?>">
  <?= bf_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($post['id'] ?? 0) ?>">
  <input type="hidden" name="content_html" id="content_html" value="<?= e($v('content_html')) ?>">
  <input type="hidden" name="content_json" id="content_json" value="">

  <div class="mb-4 flex flex-wrap items-center gap-2">
    <a class="btn btn-ghost btn-sm -ml-2" href="<?= e(bf_admin_url('posts')) ?>"><?= bf_icon('arrow-left', 'h-3.5 w-3.5') ?> Posts</a>
    <span class="badge <?= $badge[$status] ?? 'badge-gray' ?>"><?= e(ucfirst($status)) ?></span>
    <?php if ($post && $post['deleted_at']): ?><span class="badge badge-red">In trash</span><?php endif; ?>
    <span class="text-xs text-stone-500" data-save-state><?= $post ? 'Last edited ' . e(bf_ago($post['updated_at'])) . ($editorUser ? ' by ' . e($editorUser) : '') : 'Not saved yet' ?></span>
    <div class="ml-auto flex items-center gap-1.5">
      <button type="button" class="btn btn-ghost btn-sm" data-focus-toggle title="Distraction-free writing (Ctrl+Shift+F)"><?= bf_icon('layout', 'h-3.5 w-3.5') ?> Focus</button>
      <?php if ($publicUrl): ?><a class="btn btn-ghost btn-sm" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= bf_icon('external', 'h-3.5 w-3.5') ?> View</a><?php endif; ?>
    </div>
  </div>

  <?php if ($autosave): ?>
    <div class="alert alert-warning mb-4" data-autosave-banner>
      <?= bf_icon('history', 'h-4 w-4 mt-0.5 shrink-0') ?>
      <div class="flex-1">There is an autosaved version from <?= e(bf_date($autosave['created_at'], 'M j, H:i')) ?> that is newer than the saved post.
        <a class="font-semibold underline" href="<?= e(bf_admin_url('posts/' . $post['id'] . '/revisions/' . $autosave['id'])) ?>">Review and restore it</a>.</div>
    </div>
  <?php endif; ?>
  <div class="alert alert-info mb-4" data-local-recovery hidden>
    <?= bf_icon('history', 'h-4 w-4 mt-0.5 shrink-0') ?>
    <div class="flex-1">Unsaved changes from this browser were found (<span data-local-time></span>).
      <button type="button" class="font-semibold underline" data-local-restore>Restore them</button> or <button type="button" class="underline" data-local-discard>discard</button>.</div>
  </div>

  <div class="bf-editor-grid grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="min-w-0 space-y-5">
      <div class="card card-pad">
        <label class="sr-only" for="title">Title</label>
        <textarea id="title" name="title" rows="1" maxlength="255" required placeholder="Post title"
          class="w-full resize-none border-0 bg-transparent p-0 text-2xl font-semibold leading-tight tracking-tight placeholder:text-stone-300 focus:ring-0 sm:text-[2rem]<?= $inv('title') ?>" data-autogrow><?= e($v('title')) ?></textarea>
        <?= $err('title') ?>
        <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-stone-500">
          <span>Permalink:</span>
          <span class="break-all"><?= e($url->base()) ?>/</span>
          <label class="sr-only" for="slug">Slug</label>
          <input id="slug" name="slug" value="<?= e($v('slug')) ?>" placeholder="generated-from-title" maxlength="190"
                 class="h-7 min-w-[10rem] flex-1 rounded-md border border-stone-200 bg-stone-50 px-2 font-mono text-xs focus:border-brand-500 focus:ring-1 focus:ring-brand-500<?= $inv('slug') ?>" data-slug-input pattern="[a-z0-9]+(-[a-z0-9]+)*">
          <span data-slug-status class="text-xs"></span>
        </div>
        <?= $err('slug') ?>
        <?php if ($post && in_array($status, ['published', 'scheduled'], true)): ?>
          <label class="mt-2 flex items-center gap-2 text-xs text-stone-600" data-redirect-option hidden>
            <input class="checkbox" type="checkbox" name="create_redirect" value="1" checked> Create a 301 redirect from the old URL (/<?= e($post['slug']) ?>) <?= bf_setting('url.auto_redirect_slug') ? '' : '(off by default in URL settings)' ?>
          </label>
        <?php endif; ?>
      </div>

      <div class="bf-editor" id="bf-editor">
        <div class="bf-toolbar" data-toolbar role="toolbar" aria-label="Formatting"></div>
        <div class="bf-editor-content" data-editor></div>
        <textarea class="bf-html-view" data-html-view hidden spellcheck="false" aria-label="HTML source"></textarea>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 px-4 py-2 text-xs text-stone-500">
          <span><span data-word-count>0</span> words · <span data-reading-time>0</span> min read</span>
          <span data-autosave-state>Autosave on</span>
        </div>
        <noscript><p class="p-4 text-sm text-red-700">The editor requires JavaScript.</p></noscript>
      </div>
      <?= $err('content_html') ?>

      <div class="card card-pad">
        <label class="label" for="excerpt">Excerpt</label>
        <textarea class="textarea<?= $inv('excerpt') ?>" id="excerpt" name="excerpt" rows="2" maxlength="1000" data-count="160"><?= e($v('excerpt')) ?></textarea>
        <?= $err('excerpt') ?: '<p class="help">Shown on article cards and below the title. Also the default meta description. <span data-counter></span></p>' ?>
      </div>

      <section class="card bf-editor-meta" aria-labelledby="faq-title">
        <div class="card-head"><h2 id="faq-title" class="card-title">FAQ section <span class="font-normal text-stone-400">(optional)</span></h2><button type="button" class="btn btn-secondary btn-sm" data-faq-add><?= bf_icon('plus', 'h-3.5 w-3.5') ?> Add question</button></div>
        <div class="card-pad space-y-3" data-faq-list>
          <?php foreach ((array) $v('faq', []) as $i => $f): ?>
            <div class="grid gap-2 rounded-lg border border-stone-200 p-3" data-faq-item>
              <input class="input" name="faq[<?= (int) $i ?>][q]" value="<?= e($f['q'] ?? '') ?>" placeholder="Question" aria-label="Question">
              <textarea class="textarea" name="faq[<?= (int) $i ?>][a]" rows="2" placeholder="Answer" aria-label="Answer"><?= e($f['a'] ?? '') ?></textarea>
              <button type="button" class="btn btn-ghost btn-sm justify-self-end text-red-700" data-faq-remove>Remove</button>
            </div>
          <?php endforeach; ?>
          <p class="help" data-faq-empty<?= $v('faq') ? ' hidden' : '' ?>>Questions are shown at the end of the article. FAQ structured data is only output for visible questions; rich results are not guaranteed.</p>
        </div>
        <template id="tpl-faq"><div class="grid gap-2 rounded-lg border border-stone-200 p-3" data-faq-item>
          <input class="input" data-name="q" placeholder="Question" aria-label="Question">
          <textarea class="textarea" data-name="a" rows="2" placeholder="Answer" aria-label="Answer"></textarea>
          <button type="button" class="btn btn-ghost btn-sm justify-self-end text-red-700" data-faq-remove>Remove</button>
        </div></template>
      </section>

      <section class="card bf-editor-meta" aria-labelledby="seo-panel-title" data-seo-panel>
        <div class="card-head">
          <h2 id="seo-panel-title" class="card-title">Search &amp; social</h2>
          <span class="flex items-center gap-2 text-xs text-stone-500">Advisory score <span class="badge badge-gray tabular-nums" data-seo-score><?= (int) $analysis['score'] ?></span></span>
        </div>
        <div class="px-4 sm:px-5">
          <div class="tabs" role="tablist">
            <button type="button" class="tab is-active" role="tab" aria-selected="true" data-tab="seo-general">SEO</button>
            <button type="button" class="tab" role="tab" aria-selected="false" data-tab="seo-analysis">Analysis</button>
            <button type="button" class="tab" role="tab" aria-selected="false" data-tab="seo-social">Social</button>
            <button type="button" class="tab" role="tab" aria-selected="false" data-tab="seo-advanced">Advanced</button>
          </div>
        </div>
        <div class="card-pad">
          <div data-tab-panel="seo-general" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2 rounded-lg border border-stone-200 bg-white p-4 serp" aria-label="Search result preview">
              <p class="serp-url truncate" data-serp-url><?= e($analysis['serp']['url']) ?></p>
              <p class="serp-title mt-1 line-clamp-2" data-serp-title><?= e($analysis['serp']['title'] ?: 'Post title') ?></p>
              <p class="serp-desc mt-1 line-clamp-2" data-serp-desc><?= e($analysis['serp']['description']) ?></p>
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="seo_title">SEO title</label>
              <input class="input<?= $inv('seo_title') ?>" id="seo_title" name="seo_title" value="<?= e($v('seo_title')) ?>" maxlength="255" placeholder="Defaults to the title template" data-count="60">
              <?= $err('seo_title') ?: '<p class="help">Variables like {site_name} are allowed. <span data-counter></span></p>' ?>
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="meta_description">Meta description</label>
              <textarea class="textarea<?= $inv('meta_description') ?>" id="meta_description" name="meta_description" rows="2" maxlength="500" placeholder="Defaults to the excerpt" data-count="160"><?= e($v('meta_description')) ?></textarea>
              <?= $err('meta_description') ?: '<p class="help">A specific summary encourages clicks. <span data-counter></span></p>' ?>
            </div>
            <div>
              <label class="label" for="focus_keyword">Focus keyword</label>
              <input class="input" id="focus_keyword" name="focus_keyword" value="<?= e($v('focus_keyword')) ?>" maxlength="190" placeholder="e.g. airport taxi Mumbai">
              <p class="help">Used only for editorial guidance in the analysis. Not output as a meta tag.</p>
            </div>
            <div>
              <label class="label" for="secondary_keywords">Secondary keywords</label>
              <input class="input" id="secondary_keywords" name="secondary_keywords" value="<?= e($v('secondary_keywords')) ?>" maxlength="500" placeholder="comma, separated">
            </div>
          </div>

          <div data-tab-panel="seo-analysis" hidden>
            <dl class="mb-4 grid grid-cols-3 gap-2 text-center text-xs sm:grid-cols-6" data-seo-stats>
              <?php foreach (['words' => 'Words', 'reading_time' => 'Min read', 'headings' => 'Headings', 'images' => 'Images', 'internal_links' => 'Internal links', 'external_links' => 'External links'] as $k => $label): ?>
                <div class="rounded-lg bg-stone-50 px-2 py-2"><dt class="text-stone-500"><?= e($label) ?></dt><dd class="text-base font-semibold text-ink tabular-nums" data-stat="<?= e($k) ?>"><?= (int) $analysis['stats'][$k] ?></dd></div>
              <?php endforeach; ?>
            </dl>
            <ul class="divide-y divide-stone-100" data-seo-checks>
              <?php foreach ($analysis['checks'] as $c): ?>
                <li class="seo-check"><span class="dot <?= $checkColors[$c['severity']] ?>" title="<?= e(ucfirst($c['severity'])) ?>"></span><span><span class="sr-only"><?= e(ucfirst($c['severity'])) ?>: </span><?= e($c['message']) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <p class="help mt-3">Checks update as you type. The score summarises these local checks only — it does not predict Google rankings.</p>
          </div>

          <div data-tab-panel="seo-social" class="grid gap-4 sm:grid-cols-2" hidden>
            <div class="sm:col-span-2">
              <label class="label" for="og_title">Social title</label>
              <input class="input" id="og_title" name="og_title" value="<?= e($v('og_title')) ?>" maxlength="255" placeholder="Defaults to the post title">
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="og_description">Social description</label>
              <textarea class="textarea" id="og_description" name="og_description" rows="2" maxlength="500" placeholder="Defaults to the meta description"><?= e($v('og_description')) ?></textarea>
            </div>
            <div class="sm:col-span-2">
              <span class="label">Social image</span>
              <?= $this->insert('admin/partials/media-field', ['name' => 'og_image_id', 'value' => $v('og_image_id'), 'id' => 'og_image_id']) ?>
              <p class="help">Defaults to the featured image. Recommended 1200 × 630 px.</p>
            </div>
            <div>
              <label class="label" for="twitter_card">X / Twitter card</label>
              <select class="select" id="twitter_card" name="twitter_card">
                <?php foreach (['default' => 'Site default', 'summary_large_image' => 'Large image', 'summary' => 'Summary'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $v('twitter_card') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>

          <div data-tab-panel="seo-advanced" class="grid gap-4 sm:grid-cols-2" hidden>
            <div class="sm:col-span-2">
              <label class="label" for="canonical_url">Canonical URL</label>
              <input class="input<?= $inv('canonical_url') ?>" id="canonical_url" name="canonical_url" type="url" value="<?= e($v('canonical_url')) ?>" placeholder="Leave empty to use this post’s own URL">
              <?= $err('canonical_url') ?: '<p class="help">Only set this for content first published elsewhere. Posts with a foreign canonical are excluded from the sitemap.</p>' ?>
            </div>
            <div>
              <label class="label" for="robots_index">Search engine indexing</label>
              <select class="select" id="robots_index" name="robots_index">
                <?php foreach (['default' => 'Default (' . (bf_setting('seo.robots_posts_index') ? 'index' : 'noindex') . ')', 'index' => 'index', 'noindex' => 'noindex — hide from search'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $v('robots_index') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="label" for="robots_follow">Link following</label>
              <select class="select" id="robots_follow" name="robots_follow">
                <?php foreach (['default' => 'Default (follow)', 'follow' => 'follow', 'nofollow' => 'nofollow'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $v('robots_follow') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="robots_advanced">Advanced robots directives</label>
              <input class="input font-mono text-xs" id="robots_advanced" name="robots_advanced" value="<?= e($v('robots_advanced')) ?>" placeholder="e.g. max-snippet:120, noimageindex">
              <p class="help">Allowed: nosnippet, noimageindex, notranslate, noarchive, max-snippet:N, max-video-preview:N, max-image-preview:none|standard|large, unavailable_after:YYYY-MM-DD.</p>
            </div>
            <div>
              <label class="label" for="schema_type">Structured data type</label>
              <select class="select" id="schema_type" name="schema_type">
                <?php foreach (['default' => 'Default (' . bf_setting('schema.post_type') . ')', 'BlogPosting' => 'BlogPosting', 'Article' => 'Article', 'NewsArticle' => 'NewsArticle', 'none' => 'None (no article schema)'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $v('schema_type') === $k || ($k === 'none' && $post && !$post['schema_enabled']) ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </section>
    </div>

    <aside class="bf-editor-side space-y-5">
      <section class="card" aria-labelledby="publish-title">
        <div class="card-head"><h2 id="publish-title" class="card-title">Publish</h2></div>
        <div class="card-pad space-y-3">
          <div>
            <label class="label" for="published_at">Publication date <span class="font-normal text-stone-400">(<?= e($tz) ?>)</span></label>
            <input class="input<?= $inv('published_at') ?>" type="datetime-local" id="published_at" name="published_at" value="<?= e($v('published_at')) ?>">
            <?= $err('published_at') ?: '<p class="help">Leave empty to publish now. A future date schedules the post.</p>' ?>
          </div>
          <?php if (in_array($status, ['published', 'scheduled'], true)): ?>
            <label class="flex items-center gap-2 text-xs text-stone-600"><input class="checkbox" type="checkbox" name="minor_edit" value="1"> Minor edit (don’t change the “updated” date)</label>
          <?php endif; ?>
          <div class="grid grid-cols-2 gap-2 pt-1">
            <?php if ($status === 'draft'): ?>
              <button class="btn btn-secondary" type="submit" name="action" value="draft">Save draft</button>
            <?php else: ?>
              <button class="btn btn-secondary" type="submit" name="action" value="unpublish" data-confirm="Unpublish this post? It will be removed from the blog.">Unpublish</button>
            <?php endif; ?>
            <button class="btn btn-secondary" type="button" data-preview<?= $isNew ? ' data-needs-save' : '' ?>><?= bf_icon('eye', 'h-3.5 w-3.5') ?> Preview</button>
            <?php if ($canPublish): ?>
              <button class="btn btn-accent col-span-2" type="submit" name="action" value="<?= $status === 'draft' ? 'publish' : 'update' ?>" data-publish-btn>
                <?= $status === 'draft' ? 'Publish' : 'Update' ?>
              </button>
            <?php elseif ($status === 'draft'): ?>
              <p class="col-span-2 text-xs text-stone-500">An editor will review and publish your draft.</p>
            <?php endif; ?>
          </div>
          <?php if ($post): ?>
            <div class="space-y-1 border-t border-stone-100 pt-3 text-xs text-stone-500">
              <p><a class="hover:text-ink hover:underline" href="<?= e(bf_admin_url('posts/' . $post['id'] . '/revisions')) ?>"><?= bf_icon('history', 'inline h-3.5 w-3.5') ?> <?= (int) $revisionCount ?> revision(s)</a></p>
              <?php if ($shareUrl): ?><p><button type="button" class="hover:text-ink hover:underline" data-copy-text="<?= e($shareUrl) ?>"><?= bf_icon('link', 'inline h-3.5 w-3.5') ?> Copy private preview link</button></p><?php endif; ?>
              <p>Created <?= e(bf_date($post['created_at'], 'M j, Y H:i')) ?></p>
              <?php if ($post['content_modified_at']): ?><p>Content updated <?= e(bf_date($post['content_modified_at'], 'M j, Y H:i')) ?></p><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card card-pad space-y-3" aria-label="Featured image">
        <span class="label">Featured image</span>
        <?= $this->insert('admin/partials/media-field', ['name' => 'featured_image_id', 'value' => $v('featured_image_id'), 'id' => 'featured_image_id']) ?>
        <div>
          <label class="label" for="featured_image_alt">Image ALT text</label>
          <input class="input" id="featured_image_alt" name="featured_image_alt" value="<?= e($v('featured_image_alt')) ?>" maxlength="500" placeholder="Defaults to the image’s ALT text">
        </div>
        <div>
          <label class="label" for="featured_image_caption">Caption</label>
          <input class="input" id="featured_image_caption" name="featured_image_caption" value="<?= e($v('featured_image_caption')) ?>" maxlength="500">
        </div>
      </section>

      <section class="card card-pad space-y-3" aria-label="Organisation">
        <div>
          <label class="label" for="category_id">Category</label>
          <select class="select<?= $inv('category_id') ?>" id="category_id" name="category_id">
            <option value="">— None —</option>
            <?php foreach ($categories as $id => $name): ?><option value="<?= (int) $id ?>"<?= (int) $v('category_id') === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
          </select>
          <?= $err('category_id') ?>
        </div>
        <div>
          <label class="label" for="tags">Tags</label>
          <input class="input" id="tags" name="tags" value="<?= e(implode(', ', (array) $v('tags', []))) ?>" list="tag-list" placeholder="comma, separated" autocomplete="off" data-tags-input>
          <datalist id="tag-list"><?php foreach ($allTags as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
          <p class="help">New tags are created automatically.</p>
        </div>
        <?php if ($canChooseAuthor): ?>
          <div>
            <label class="label" for="author_id">Author</label>
            <select class="select<?= $inv('author_id') ?>" id="author_id" name="author_id">
              <option value="">— None —</option>
              <?php foreach ($authors as $id => $name): ?><option value="<?= (int) $id ?>"<?= (int) $v('author_id') === $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <label class="flex items-center gap-2.5 text-sm"><input type="hidden" name="is_featured" value="0"><input class="switch" type="checkbox" role="switch" name="is_featured" value="1"<?= $v('is_featured') ? ' checked' : '' ?><?= bf_can('posts.edit_all') ? '' : ' disabled' ?>> Featured post</label>
        <div>
          <label class="label" for="show_toc">Table of contents</label>
          <select class="select" id="show_toc" name="show_toc">
            <?php foreach (['default' => 'Site default', '1' => 'Show', '0' => 'Hide'] as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $v('show_toc', 'default') === (string) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
      </section>

      <section class="card card-pad" aria-labelledby="related-title" data-related>
        <h2 id="related-title" class="label">Related articles</h2>
        <ul class="mb-2 space-y-1" data-related-list>
          <?php foreach ($related as $r): ?>
            <li class="flex items-center gap-2 rounded-md bg-stone-50 px-2 py-1.5 text-xs" data-related-item><input type="hidden" name="related_ids[]" value="<?= (int) $r['id'] ?>"><span class="min-w-0 flex-1 truncate"><?= e($r['title']) ?></span><button type="button" class="icon-btn h-6 w-6" data-related-remove aria-label="Remove <?= e($r['title']) ?>"><?= bf_icon('x', 'h-3.5 w-3.5') ?></button></li>
          <?php endforeach; ?>
        </ul>
        <label class="sr-only" for="related-search">Search posts to relate</label>
        <input class="input h-8 text-xs" id="related-search" type="search" placeholder="Search posts to add…" autocomplete="off" data-related-search>
        <ul class="mt-1 max-h-48 overflow-y-auto rounded-md border border-stone-200 bg-white text-xs shadow-sm" data-related-results hidden></ul>
        <p class="help">Optional. Otherwise related posts are chosen by category and tags.</p>
      </section>

      <?php if ($post && !$post['deleted_at']): ?>
        <div class="flex flex-wrap gap-2 px-1">
          <button class="btn btn-ghost btn-sm" type="submit" form="post-duplicate"><?= bf_icon('copy', 'h-3.5 w-3.5') ?> Duplicate</button>
          <button class="btn btn-ghost btn-sm text-red-700" type="submit" form="post-trash" data-confirm="Move this post to the trash?"><?= bf_icon('trash', 'h-3.5 w-3.5') ?> Move to trash</button>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</form>
<?php if ($post): ?>
  <form id="post-duplicate" method="post" action="<?= e(bf_admin_url('posts/' . $post['id'] . '/duplicate')) ?>" hidden><?= bf_csrf_field() ?></form>
  <form id="post-trash" method="post" action="<?= e(bf_admin_url('posts/' . $post['id'] . '/trash')) ?>" hidden><?= bf_csrf_field() ?></form>
<?php endif; ?>
<?php $this->start('scripts'); ?>
<script src="<?= e(bf_asset('js/editor.js')) ?>" defer></script>
<?php $this->stop(); ?>
