<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Installer locked</title>
<link rel="stylesheet" href="<?= e($assetBase) ?>/css/admin.css"></head>
<body class="grid min-h-screen place-items-center p-6">
  <main class="card card-pad max-w-md text-center">
    <h1 class="page-title">BlogFlow is already installed</h1>
    <p class="mt-2 text-sm text-stone-600">For security the installer is locked. To reinstall, delete <code>storage/installed.lock</code> and the configuration file using FTP or your file manager, and use an empty database.</p>
    <a class="btn btn-primary mt-5" href="<?= e($adminUrl) ?>">Go to the admin</a>
  </main>
</body>
</html>
