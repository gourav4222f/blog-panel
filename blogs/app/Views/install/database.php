<?php $this->layout('install/layout'); $d = $data['db'] ?? []; ?>
<h1 class="page-title">Database connection</h1>
<p class="page-sub">Create an empty MySQL 8 / MariaDB database and user in your hosting panel, then enter the details.</p>
<form method="post" class="mt-6 grid gap-4 sm:grid-cols-2" autocomplete="off">
  <input type="hidden" name="_token" value="<?= e($token) ?>">
  <?= $this->insert('install/field', ['name' => 'host', 'label' => 'Database host', 'value' => $d['host'] ?? 'localhost', 'required' => true, 'help' => 'Usually "localhost".']) ?>
  <?= $this->insert('install/field', ['name' => 'port', 'label' => 'Port', 'value' => $d['port'] ?? '3306', 'type' => 'number']) ?>
  <?= $this->insert('install/field', ['name' => 'name', 'label' => 'Database name', 'value' => $d['name'] ?? '', 'required' => true]) ?>
  <?= $this->insert('install/field', ['name' => 'user', 'label' => 'Username', 'value' => $d['user'] ?? '', 'required' => true]) ?>
  <?= $this->insert('install/field', ['name' => 'pass', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
  <?= $this->insert('install/field', ['name' => 'prefix', 'label' => 'Table prefix', 'value' => $d['prefix'] ?? 'bf_', 'help' => 'Lets several installations share one database.']) ?>
  <div class="sm:col-span-2 flex items-center justify-between pt-2">
    <a class="btn btn-ghost" href="?step=requirements">Back</a>
    <button class="btn btn-primary" type="submit">Test connection &amp; continue</button>
  </div>
</form>
