<?php $this->layout('install/layout'); $a = $data['admin'] ?? []; ?>
<h1 class="page-title">Administrator account</h1>
<p class="page-sub">There are no default accounts. Choose a strong, unique password.</p>
<form method="post" class="mt-6 grid gap-4 sm:grid-cols-2">
  <input type="hidden" name="_token" value="<?= e($token) ?>">
  <?= $this->insert('install/field', ['name' => 'name', 'label' => 'Your name', 'value' => $a['name'] ?? '', 'required' => true, 'autocomplete' => 'name', 'help' => 'Also used as your public author name.']) ?>
  <?= $this->insert('install/field', ['name' => 'email', 'label' => 'Email', 'value' => $a['email'] ?? '', 'type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
  <?= $this->insert('install/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => 'At least 10 characters. A passphrase of several words works well.']) ?>
  <?= $this->insert('install/field', ['name' => 'password_confirm', 'label' => 'Confirm password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <div class="sm:col-span-2 flex items-center justify-between pt-2">
    <a class="btn btn-ghost" href="?step=site">Back</a>
    <button class="btn btn-primary" type="submit">Continue</button>
  </div>
</form>
