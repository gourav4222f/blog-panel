<?php $this->layout('install/layout'); $d = $data['db']; $s = $data['site']; ?>
<h1 class="page-title">Ready to install</h1>
<p class="page-sub">Review the details below. Installing creates the database tables, a private configuration file and your administrator account.</p>
<dl class="mt-6 grid gap-x-6 gap-y-3 rounded-lg border border-stone-200 p-4 text-sm sm:grid-cols-[10rem_1fr]">
  <dt class="text-stone-500">Database</dt><dd class="font-medium break-all"><?= e($d['user']) ?>@<?= e($d['host']) ?>:<?= (int) $d['port'] ?>/<?= e($d['name']) ?> (prefix <code><?= e($d['prefix']) ?></code>)</dd>
  <dt class="text-stone-500">Blog URL</dt><dd class="font-medium break-all"><?= e($s['blog_url']) ?>/</dd>
  <dt class="text-stone-500">Blog name</dt><dd class="font-medium"><?= e($s['blog_name']) ?></dd>
  <dt class="text-stone-500">Main website</dt><dd class="font-medium break-all"><?= e($s['main_site_url'] ?: '—') ?></dd>
  <dt class="text-stone-500">Administrator</dt><dd class="font-medium"><?= e($data['admin']['name']) ?> &lt;<?= e($data['admin']['email']) ?>&gt;</dd>
</dl>
<form method="post" class="mt-6 flex items-center justify-between">
  <input type="hidden" name="_token" value="<?= e($token) ?>">
  <a class="btn btn-ghost" href="?step=admin">Back</a>
  <button class="btn btn-accent" type="submit">Install BlogFlow</button>
</form>
