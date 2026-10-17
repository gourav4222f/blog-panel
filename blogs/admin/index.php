<?php

// Serves /blogs/admin/ when the web server maps the directory index directly.
define('BF_ENTRY_SUBDIR', true);
require dirname(__DIR__) . '/index.php';
