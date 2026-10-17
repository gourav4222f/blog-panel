#!/bin/sh
# Recreates a fresh, uninstalled copy of /blogs/ at /var/www/site2/blogs and an empty database
# for the end-to-end tests (Apache vhost blog.test:8081 with open_basedir = /blogs only).
set -e
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
SRC=${BF_SOURCE:-$ROOT/blogs}
DB=${BF_DB:-blogflow2}
mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -rf /var/www/site2/blogs
mkdir -p /var/www/site2
rsync -a --exclude 'config/config.php' --exclude 'storage/installed.lock' --exclude 'storage/cache/*' \
  --exclude 'storage/logs/*' --exclude 'storage/sessions/*' --exclude 'storage/backups/*' \
  --exclude 'storage/originals/*' --exclude 'uploads/20*' "$SRC/" /var/www/site2/blogs/
chown -R www-data:www-data /var/www/site2/blogs/storage /var/www/site2/blogs/uploads /var/www/site2/blogs/config
rm -f /tmp/apache2-error.log
apachectl graceful 2>/dev/null || true
sleep 1
echo "site2 reset"
