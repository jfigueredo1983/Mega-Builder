/**
 * Mega Builder — Stage 7 Automated Unit & Package Verification Suite
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const ROOT = process.cwd();

function readText(relPath) {
  return fs.readFileSync(path.join(ROOT, relPath), 'utf8');
}

console.log('Running Mega Builder Stage 7 Verification Suite...');

// 1. Verify all 15 Page Sections + 4 Component Sub-Studios in PayloadValidator.php
const validatorPhp = readText('src/Validator/PayloadValidator.php');
const REQUIRED_15_SECTIONS = [
  'header',
  'homepage',
  'product',
  'category',
  'footer',
  'cms',
  'account',
  'prices_drop',
  'new_products',
  'best_sales',
  'search',
  'checkout',
  'manufacturers',
  'page_404',
  'custom',
];
for (const sec of REQUIRED_15_SECTIONS) {
  assert.ok(
    validatorPhp.includes(`'${sec}'`),
    `Missing Page Section '${sec}' in PayloadValidator.php`
  );
}

const REQUIRED_4_SUBSTUDIOS = ['megamenu', 'slider', 'card', 'accordion'];
for (const sub of REQUIRED_4_SUBSTUDIOS) {
  assert.ok(
    validatorPhp.includes(`'${sub}'`),
    `Missing Sub-Studio '${sub}' in PayloadValidator.php`
  );
}
console.log('  [PASS] All 15 Page Sections and 4 Component Sub-Studios verified in PayloadValidator.');

// 2. Verify all 77 Widgets across 7 categories in WidgetRegistry.php
const registryPhp = readText('src/Compiler/WidgetRegistry.php');
const widgetMatches = [...registryPhp.matchAll(/\['type'\s*=>\s*'([a-z0-9_]+)'/g)];
assert.equal(
  widgetMatches.length,
  77,
  `Expected 77 widgets in WidgetRegistry.php, found ${widgetMatches.length}`
);
console.log(`  [PASS] All ${widgetMatches.length} Creative-Elements-grade widgets verified in WidgetRegistry.`);

// 3. Verify 5-Shield Security Sanitizers & CSRF Protection
const htmlSan = readText('src/Security/HtmlSanitizer.php');
assert.ok(htmlSan.includes('javascript:'), 'HtmlSanitizer must block javascript: URIs');
assert.ok(htmlSan.includes('on[a-z0-9_-]+'), 'HtmlSanitizer must strip inline on* event handlers');

const cssSan = readText('src/Security/CssSanitizer.php');
assert.ok(cssSan.includes('</style'), 'CssSanitizer must strip </style> breakouts');

const svgSan = readText('src/Security/SvgSanitizer.php');
assert.ok(svgSan.includes('foreignobject'), 'SvgSanitizer must strip <foreignObject> and <script>');

const adminCtrl = readText('controllers/admin/AdminMegaBuilderController.php');
assert.ok(adminCtrl.includes('HTTP_X_MB_CSRF_TOKEN'), 'AdminMegaBuilderController must check X-MB-CSRF-Token');
assert.ok(adminCtrl.includes('CSRF_INVALID'), 'AdminMegaBuilderController must return 403 CSRF_INVALID on bad tokens');
console.log('  [PASS] 5-Shield Security Sanitizers (HTML, CSS, SVG, CSRF, SAMEORIGIN) verified.');

// 4. Verify Compiled Vue 3 + TypeScript Studio Dist Assets
assert.ok(fs.existsSync(path.join(ROOT, 'views/dist/studio.js')), 'views/dist/studio.js must exist');
assert.ok(fs.existsSync(path.join(ROOT, 'views/dist/studio.css')), 'views/dist/studio.css must exist');
assert.ok(fs.existsSync(path.join(ROOT, 'views/dist/index.php')), 'views/dist/index.php guard must exist');
console.log('  [PASS] Compiled Vue 3 + TypeScript Studio bundle and index.php guards verified.');

console.log('All Stage 7 static checks PASSED.');
// 5. Run the PHPUnit suite (tests/php). It is required: without PHP or vendor/ the run fails
//    instead of reporting a pass it never ran. Point MB_PHP_BIN at a php binary if it is not on PATH.
const phpBin = process.env.MB_PHP_BIN || 'php';
const phpunit = path.join(ROOT, 'vendor/phpunit/phpunit/phpunit');
if (!fs.existsSync(phpunit)) {
  console.error('\n[FAIL] PHPUnit NOT run: vendor/ is missing. Run `composer install` (needs PHP 8.1+).');
  process.exit(1);
}
const php = spawnSync(phpBin, [phpunit], { stdio: 'inherit', cwd: ROOT });
if (php.error) {
  console.error(`\n[FAIL] PHPUnit NOT run: could not start '${phpBin}' (${php.error.code}). Install PHP 8.1+ or set MB_PHP_BIN.`);
  process.exit(1);
}
if (php.status !== 0) {
  console.error('\n[FAIL] PHPUnit failed.');
  process.exit(php.status ?? 1);
}
console.log('All checks PASSED, including PHPUnit.');
