<?php $this->layout('admin/layout'); ?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="page-title">Security</h1><p class="page-sub">Login protection, sessions and installation checks.</p></div>
  <a class="btn btn-secondary" href="<?= e(bf_admin_url('settings/security')) ?>"><?= bf_icon('settings') ?> Security settings</a>
</div>
<div class="grid gap-5 lg:grid-cols-2">
  <section class="card">
    <div class="card-head"><h2 class="card-title">Hardening checklist</h2></div>
    <ul class="divide-y divide-stone-100">
      <?php foreach ($checks as [$label, $ok, $help]): ?>
        <li class="flex gap-3 px-5 py-3 text-sm"><span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] <?= $ok ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' ?>"><?= $ok ? '✓' : '!' ?></span><div><p class="font-medium"><?= e($label) ?></p><p class="text-xs text-stone-500"><?= e($help) ?></p></div></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="card">
    <div class="card-head"><h2 class="card-title">Policy in effect</h2></div>
    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 card-pad text-sm">
      <dt class="text-stone-500">Lockout</dt><dd><?= (int) bf_setting('security.login_max_attempts') ?> failed attempts per account within <?= (int) bf_setting('security.login_lockout_minutes') ?> min</dd>
      <dt class="text-stone-500">Idle sign-out</dt><dd><?= (int) bf_setting('security.session_idle_minutes') ?> minutes</dd>
      <dt class="text-stone-500">Max session</dt><dd><?= (int) bf_setting('security.session_max_hours') ?> hours</dd>
      <dt class="text-stone-500">Passwords</dt><dd>At least <?= (int) bf_setting('security.min_password_length') ?> characters, hashed with <?= e(PASSWORD_DEFAULT === PASSWORD_BCRYPT || PASSWORD_DEFAULT === '2y' ? 'bcrypt' : 'Argon2') ?></dd>
      <dt class="text-stone-500">Protections</dt><dd>CSRF tokens, session fixation prevention, secure/HttpOnly/SameSite cookies, Content-Security-Policy in admin, audit log</dd>
    </dl>
    <?php if ($locked): ?>
      <div class="border-t border-stone-100 px-5 py-3"><p class="text-sm font-medium text-amber-800">Currently throttled IP addresses</p>
        <ul class="mt-1 text-xs text-stone-600"><?php foreach ($locked as $l): ?><li><span class="font-mono"><?= e($l['ip']) ?></span> — <?= (int) $l['n'] ?> failures, last <?= e(bf_ago($l['last'])) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </section>
  <section class="card lg:col-span-2">
    <div class="card-head"><h2 class="card-title">Recent sign-in attempts</h2></div>
    <div class="overflow-x-auto"><table class="table"><thead><tr><th>When</th><th>IP address</th><th>Result</th></tr></thead><tbody>
      <?php foreach ($attempts as $a): ?><tr><td class="text-stone-600"><?= e(bf_date($a['created_at'], 'M j, H:i:s')) ?></td><td class="font-mono text-xs"><?= e($a['ip']) ?></td><td><?= $a['success'] ? '<span class="badge badge-green">Success</span>' : '<span class="badge badge-red">Failed</span>' ?></td></tr><?php endforeach; ?>
      <?php if (!$attempts): ?><tr><td colspan="3" class="py-8 text-center text-stone-500">No attempts recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</div>
