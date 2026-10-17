#!/bin/sh
# Builds the production release: dist/blogflow-cms-<version>.zip containing a ready-to-upload blogs/ folder.
# Requires (on the development machine only): Node.js 18+, npm, Composer, zip, rsync.
set -e
ROOT=$(cd "$(dirname "$0")/.." && pwd)
cd "$ROOT"
VERSION=$(sed -n "s/^define('BF_VERSION', '\(.*\)');/\1/p" blogs/app/bootstrap.php)
OUT="$ROOT/dist/blogflow-cms-$VERSION"

if [ "${SKIP_BUILD:-0}" != "1" ]; then
  npm ci --no-audit --no-fund
  npm run build
  (cd blogs && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction)
fi

rm -rf "$OUT" "$OUT.zip"
mkdir -p "$OUT"
rsync -a \
  --exclude 'config/config.php' --exclude 'storage/installed.lock' --exclude 'storage/install.lock.tmp' \
  --exclude 'storage/cache/*' --exclude 'storage/logs/*' --exclude 'storage/sessions/*' \
  --exclude 'storage/backups/*' --exclude 'storage/originals/*' --exclude 'storage/tmp/*' \
  --exclude 'uploads/*' --include 'uploads/.htaccess' \
  --exclude '*.LEGAL.txt' --exclude '.DS_Store' \
  blogs/ "$OUT/blogs/"
# Remove third-party development files that are never needed at runtime.
for p in vendor/ezyang/htmlpurifier/tests vendor/ezyang/htmlpurifier/smoketests vendor/ezyang/htmlpurifier/maintenance \
         vendor/ezyang/htmlpurifier/docs vendor/ezyang/htmlpurifier/configdoc vendor/ezyang/htmlpurifier/art vendor/ezyang/htmlpurifier/benchmarks \
         vendor/phpmailer/phpmailer/test vendor/phpmailer/phpmailer/examples vendor/phpmailer/phpmailer/docs \
         vendor/phpmailer/phpmailer/get_oauth_token.php; do
  rm -rf "$OUT/blogs/$p"
done
find "$OUT/blogs/vendor" -name '.git' -type d -prune -exec rm -rf {} +
# Keep empty runtime folders in the archive.
for d in storage/cache storage/logs storage/sessions storage/backups storage/originals storage/tmp uploads; do
  mkdir -p "$OUT/blogs/$d"
  touch "$OUT/blogs/$d/.gitkeep"
done
cp blogs/uploads/.htaccess "$OUT/blogs/uploads/.htaccess"
cp docs/nginx-blogs.conf "$OUT/nginx-blogs.conf"
(cd "$OUT" && zip -qr "../blogflow-cms-$VERSION.zip" .)
echo "Release: dist/blogflow-cms-$VERSION.zip ($(du -h "$ROOT/dist/blogflow-cms-$VERSION.zip" | cut -f1))"
