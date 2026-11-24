/**
 * Mega Builder — Stage 7 Production PrestaShop .zip Module Packager
 * Packages the self-contained megabuilder module into dist/megabuilder.zip
 * ready for 1-click upload in any PrestaShop 8.0 to 9.2+ Back-Office.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const DIST_DIR = path.join(ROOT, 'dist');
const STAGE_ROOT = path.join(DIST_DIR, '_stage');
const MODULE_STAGE = path.join(STAGE_ROOT, 'megabuilder');
const ZIP_PATH = path.join(DIST_DIR, 'megabuilder.zip');

const INCLUDE_ITEMS = [
  'megabuilder.php',
  'config.xml',
  'index.php',
  'controllers',
  'sql',
  'src',
  'views',
];

function copyRecursive(src, dest) {
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    fs.mkdirSync(dest, { recursive: true });
    for (const entry of fs.readdirSync(src)) {
      copyRecursive(path.join(src, entry), path.join(dest, entry));
    }
  } else {
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(src, dest);
  }
}

if (fs.existsSync(STAGE_ROOT)) {
  fs.rmSync(STAGE_ROOT, { recursive: true, force: true });
}
fs.mkdirSync(MODULE_STAGE, { recursive: true });

for (const item of INCLUDE_ITEMS) {
  const srcPath = path.join(ROOT, item);
  if (fs.existsSync(srcPath)) {
    copyRecursive(srcPath, path.join(MODULE_STAGE, item));
  }
}

if (fs.existsSync(ZIP_PATH)) {
  fs.unlinkSync(ZIP_PATH);
}

// Create zip archive using tar (built into Windows 10/11 & Linux/macOS)
execSync(`tar -a -c -f "${ZIP_PATH}" -C "${STAGE_ROOT}" megabuilder`, {
  stdio: 'inherit',
});

fs.rmSync(STAGE_ROOT, { recursive: true, force: true });

const sizeKb = (fs.statSync(ZIP_PATH).size / 1024).toFixed(1);
console.log(`[package-module] Created 1-click installable PrestaShop archive: ${ZIP_PATH} (${sizeKb} KB)`);
