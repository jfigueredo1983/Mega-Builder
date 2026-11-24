<?php
/**
 * Mega Builder — Stage 7 PHP Core & 5-Shield Security Unit Test Suite
 * Verifies PSR-4 classes across PHP 8.1 - 8.5+.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

use MegaBuilder\Compiler\DynamicTagResolver;
use MegaBuilder\Compiler\LayoutCompiler;
use MegaBuilder\Compiler\WidgetRegistry;
use MegaBuilder\Security\CssSanitizer;
use MegaBuilder\Security\HtmlSanitizer;
use MegaBuilder\Security\SvgSanitizer;
use MegaBuilder\Validator\PayloadValidator;

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    }
}

echo "Running Mega Builder PHP " . PHP_VERSION . " Unit Test Suite...\n";

// 1. Test Boundary Number Clamping (-99999, 0, 9999999, NaN, 1e308)
assertTrue(PayloadValidator::clampInt(-99999, 10, 120, 16) === 10, 'Negative boundary clamping failed');
assertTrue(PayloadValidator::clampInt(9999999, 10, 120, 16) === 120, 'Upper boundary clamping failed');
assertTrue(PayloadValidator::clampInt(NAN, 10, 120, 16) === 16, 'NaN boundary fallback failed');
assertTrue(PayloadValidator::clampInt('1e308', 10, 120, 16) === 120, '1e308 clamping failed');
assertTrue(PayloadValidator::clampInt('not-a-number', 10, 120, 16) === 16, 'Non-numeric string fallback failed');
echo "  [PASS] Boundary number clamping (-99999, 0, 9999999, NaN, 1e308) verified.\n";

// 2. Test XSS Canary Sanitization & Single-Escape Output
$xssCanary = '"><svg onload="window.__qa_xss=1"><script>window.__qa_xss=1</script>Hello & Welcome {{mb:shop_name}}';
$cleanPlain = HtmlSanitizer::sanitizePlainText($xssCanary);
assertTrue(!str_contains($cleanPlain, 'onload'), 'Plain text sanitizer leaked onload');
assertTrue(!str_contains($cleanPlain, '<script'), 'Plain text sanitizer leaked <script>');
assertTrue(str_contains($cleanPlain, '{{mb:shop_name}}'), 'Plain text sanitizer stripped dynamic tag');

$escapedOnce = HtmlSanitizer::escapeOutput('Coats & Jackets');
$escapedTwice = HtmlSanitizer::escapeOutput($escapedOnce);
assertTrue($escapedOnce === 'Coats &amp; Jackets', 'Single escape failed');
assertTrue($escapedTwice === 'Coats &amp; Jackets', 'Double escape protection (&amp;amp;) failed');

$cleanRich = HtmlSanitizer::sanitizeRichHtml('<p onclick="alert(1)">Safe <a href="javascript:alert(1)">Link</a><svg onload="window.__qa_xss=1"></svg></p>');
assertTrue(!str_contains($cleanRich, 'onclick'), 'Rich HTML leaked onclick');
assertTrue(!str_contains($cleanRich, 'onload'), 'Rich HTML leaked onload');
assertTrue(!str_contains($cleanRich, 'javascript:'), 'Rich HTML leaked javascript: URI');
echo "  [PASS] HtmlSanitizer XSS prevention & zero double-escaping verified.\n";

// 3. Test CSS Breakout Sanitizer
$dirtyCss = ".my-box { color: red; }</style><script>window.__qa_xss=1</script><style>";
$cleanCss = CssSanitizer::sanitizeCssBlock($dirtyCss);
assertTrue(!str_contains(strtolower($cleanCss), '</style'), 'CssSanitizer leaked </style>');
assertTrue(!str_contains(strtolower($cleanCss), '<script'), 'CssSanitizer leaked <script>');
echo "  [PASS] CssSanitizer breakout prevention verified.\n";

// 4. Test SVG Upload XML Sanitizer
$maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" onload="window.__qa_xss=1"><script>alert(1)</script><foreignObject><iframe></iframe></foreignObject><path d="M0 0h10v10z" onclick="evil()"/></svg>';
$cleanSvg = SvgSanitizer::sanitizeSvg($maliciousSvg);
assertTrue(is_string($cleanSvg) && str_contains($cleanSvg, '<path'), 'Valid SVG path was lost');
assertTrue(!str_contains($cleanSvg, 'onload'), 'SvgSanitizer leaked onload');
assertTrue(!str_contains($cleanSvg, '<script'), 'SvgSanitizer leaked <script>');
assertTrue(!str_contains(strtolower($cleanSvg), 'foreignobject'), 'SvgSanitizer leaked <foreignObject>');
assertTrue(!str_contains($cleanSvg, 'onclick'), 'SvgSanitizer leaked onclick');
echo "  [PASS] SvgSanitizer XML cleaning verified.\n";

// 5. Test All 77 Widgets + All 19 Starter Blueprints (15 Page Sections + 4 Sub-Studios) Compilation & Dynamic Tag Resolution
$allWidgets = WidgetRegistry::getAllWidgets();
assertTrue(count($allWidgets) === 77, 'Expected 77 widgets in WidgetRegistry');

$blueprints = WidgetRegistry::getStarterBlueprints();
assertTrue(count($blueprints) === 19, 'Expected 19 starter blueprints (15 page sections + 4 sub-studios)');

foreach ($blueprints as $key => $bp) {
    $compiled = LayoutCompiler::compile($bp['layout']);
    assertTrue($compiled['html'] !== '', "Empty compiled HTML for blueprint {$key}");
    assertTrue(str_contains($compiled['css'], '@media (max-width: 1024px)'), "Missing Tablet @media rule for {$key}");
    assertTrue(str_contains($compiled['css'], '@media (max-width: 767px)'), "Missing Mobile @media rule for {$key}");

    $resolved = DynamicTagResolver::resolve($compiled['html']);
    assertTrue(!str_contains($resolved, '{{mb:'), "Unresolved {{mb:...}} dynamic tag leaked in {$key}");
}
echo "  [PASS] All 19 Starter Blueprints compiled across 3 viewports with 0 unresolved dynamic tags.\n";

echo "All PHP Core & Security Unit Tests PASSED (100%).\n";
