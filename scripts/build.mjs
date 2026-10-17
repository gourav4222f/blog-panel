// BlogFlow CMS asset build. Development-only: the production server never needs Node.js.
//   npm run build        -> CSS + JS + fonts into blogs/assets
//   npm run watch        -> rebuild on change
import { execFileSync, spawn } from 'node:child_process';
import { cpSync, mkdirSync, existsSync, writeFileSync, readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = (p) => resolve(root, 'blogs/assets', p);
const mode = process.argv[2] || 'all';
const tw = resolve(root, 'node_modules/.bin/tailwindcss');

function css(watch = false) {
  const jobs = [
    ['tailwind.front.config.js', 'src/css/front.css', 'css/front.css'],
    ['tailwind.admin.config.js', 'src/css/admin.css', 'css/admin.css'],
  ];
  for (const [config, input, output] of jobs) {
    const args = ['-c', config, '-i', input, '-o', out(output), '--minify'];
    if (watch) {
      spawn(tw, [...args, '--watch'], { cwd: root, stdio: 'inherit' });
    } else {
      execFileSync(tw, args, { cwd: root, stdio: 'inherit' });
    }
  }
}

const jsEntries = {
  'js/front': 'src/js/front.js',
  'js/admin': 'src/js/admin/index.js',
  'js/editor': 'src/js/editor/index.js',
  'js/install': 'src/js/install.js',
};

async function js(watch = false) {
  const options = {
    entryPoints: Object.fromEntries(Object.entries(jsEntries).map(([k, v]) => [k, resolve(root, v)])),
    outdir: resolve(root, 'blogs/assets'),
    bundle: true,
    minify: true,
    format: 'iife',
    target: ['es2019'],
    legalComments: 'linked',
    sourcemap: false,
    logLevel: 'info',
  };
  if (watch) {
    const ctx = await esbuild.context(options);
    await ctx.watch();
  } else {
    await esbuild.build(options);
  }
}

const FONTS = {
  inter: true, 'dm-sans': true, manrope: false, 'source-serif-4': true, lora: true,
  merriweather: true, fraunces: true, 'playfair-display': true,
};

function fonts() {
  mkdirSync(out('fonts'), { recursive: true });
  let licenses = 'Self-hosted fonts bundled with BlogFlow CMS (via Fontsource). All fonts are licensed under the SIL Open Font License 1.1.\n\n';
  for (const [name, italic] of Object.entries(FONTS)) {
    const dir = resolve(root, 'node_modules/@fontsource-variable', name, 'files');
    for (const subset of ['latin', 'latin-ext']) {
      for (const style of italic ? ['normal', 'italic'] : ['normal']) {
        const file = `${name}-${subset}-wght-${style}.woff2`;
        if (!existsSync(resolve(dir, file))) {
          throw new Error(`Missing font file ${file}`);
        }
        cpSync(resolve(dir, file), out('fonts/' + file));
      }
    }
    const pkg = JSON.parse(readFileSync(resolve(root, 'node_modules/@fontsource-variable', name, 'package.json'), 'utf8'));
    licenses += `- ${name}: ${pkg.license || 'OFL-1.1'} — ${pkg.homepage || ''}\n`;
  }
  writeFileSync(out('fonts/LICENSES.txt'), licenses);
}

if (mode === 'css') css();
else if (mode === 'js') await js();
else if (mode === 'fonts') fonts();
else if (mode === 'watch') { css(true); await js(true); }
else { fonts(); css(); await js(); }
