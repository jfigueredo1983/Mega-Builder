/**
 * Mega Builder — Stage 2 & Stage 7 PrestaShop Directory Traversal Guard Stamper
 * Ensures every module subdirectory contains an index.php security redirect file.
 */

import fs from 'node:fs';
import path from 'node:path';

const ROOT = process.cwd();

const INDEX_GUARD_PHP = `<?php
/**
 * Mega Builder — PrestaShop Directory Security Guard
 */
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: ../');
exit;
`;

const GUARDED_DIRS = [
  '.',
  'controllers',
  'controllers/admin',
  'controllers/front',
  'sql',
  'src',
  'src/Compiler',
  'src/Repository',
  'src/Security',
  'src/Service',
  'src/Validator',
  'views',
  'views/css',
  'views/dist',
  'views/img',
  'views/img/uploads',
  'views/js',
  'views/templates',
  'views/templates/admin',
  'views/templates/front',
  'views/templates/hook',
];

let stamped = 0;
for (const rel of GUARDED_DIRS) {
  const fullDir = path.join(ROOT, rel);
  if (!fs.existsSync(fullDir)) {
    fs.mkdirSync(fullDir, { recursive: true });
  }
  const indexFile = path.join(fullDir, 'index.php');
  if (!fs.existsSync(indexFile)) {
    fs.writeFileSync(indexFile, INDEX_GUARD_PHP, 'utf8');
    stamped++;
  }
}

console.log(`[postbuild-guards] Verified index.php security guards across ${GUARDED_DIRS.length} directories (${stamped} newly created).`);
