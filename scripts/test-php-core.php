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
use MegaBuilder\Security\PreviewToken;
use MegaBuilder\Repository\TemplateRepository;
use MegaBuilder\Service\MediaManagerService;
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

// 6. Signed preview tokens: bound to one template, expire, reject tampering, fail closed without a secret
$secret = 'test-secret-value';
$now = 1_800_000_000;
$tok = PreviewToken::create(5, $secret, 900, $now);
assertTrue(PreviewToken::verify(5, $tok, $secret, $now + 10), 'Valid preview token rejected');
assertTrue(!PreviewToken::verify(6, $tok, $secret, $now + 10), 'Preview token accepted for a different template');
assertTrue(!PreviewToken::verify(5, $tok, $secret, $now + 901), 'Expired preview token accepted');
assertTrue(!PreviewToken::verify(5, $tok, 'other-secret', $now + 10), 'Preview token accepted with wrong secret');
assertTrue(!PreviewToken::verify(5, '', $secret, $now), 'Empty preview token accepted');
assertTrue(!PreviewToken::verify(5, substr($tok, 0, -1) . (substr($tok, -1) === 'a' ? 'b' : 'a'), $secret, $now + 10), 'Tampered preview token accepted');
assertTrue(PreviewToken::create(5, '', 900, $now) === '', 'Token created without a secret');
assertTrue(!PreviewToken::verify(5, $tok, '', $now + 10), 'Token verified without a secret');
echo "  [PASS] Signed, expiring, template-bound preview tokens verified.\n";

// 7. URL sanitizer blocks off-site and script schemes
assertTrue(HtmlSanitizer::sanitizeUrl('//evil.example/x') === '#', 'Protocol-relative URL allowed');
assertTrue(HtmlSanitizer::sanitizeUrl('/\\evil.example') === '#', 'Backslash host trick allowed');
assertTrue(HtmlSanitizer::sanitizeUrl(' JaVaScRiPt:alert(1)') === '#', 'javascript: URL allowed');
assertTrue(HtmlSanitizer::sanitizeUrl('data:text/html,x') === '#', 'data: URL allowed');
assertTrue(HtmlSanitizer::sanitizeUrl('ftp://x.example') === '#', 'Unlisted scheme allowed');
assertTrue(HtmlSanitizer::sanitizeUrl('/2-home') === '/2-home', 'Same-site path rejected');
assertTrue(HtmlSanitizer::sanitizeUrl('https://shop.example/a?b=1') === 'https://shop.example/a?b=1', 'https URL rejected');
assertTrue(HtmlSanitizer::sanitizeUrl('{{mb:product_url}}') === '{{mb:product_url}}', 'Dynamic tag URL rejected');
assertTrue(HtmlSanitizer::sanitizeUrl('mailto:hi@example.com') === 'mailto:hi@example.com', 'mailto rejected');
echo "  [PASS] URL sanitizer scheme and off-site checks verified.\n";

// 8. Page targeting: page-specific sections only render on their page; specific IDs match the viewed entity
$productPage = ['section' => 'product', 'entity_id' => 42];
$homePage = ['section' => 'homepage', 'entity_id' => 0];
assertTrue(TemplateRepository::appliesToPage('product', 'all', [], $productPage), 'Product template hidden on product page');
assertTrue(!TemplateRepository::appliesToPage('product', 'all', [], $homePage), 'Product template shown on homepage');
assertTrue(TemplateRepository::appliesToPage('header', 'all', [], $productPage), 'Global header hidden on product page');
assertTrue(TemplateRepository::appliesToPage('product', 'specific_ids', [42, 7], $productPage), 'Targeted product not matched');
assertTrue(!TemplateRepository::appliesToPage('product', 'specific_ids', [7], $productPage), 'Untargeted product matched');
assertTrue(!TemplateRepository::appliesToPage('homepage', 'specific_ids', [1], $homePage), 'specific_ids matched a page with no entity id');
echo "  [PASS] Page section and specific-ID targeting verified.\n";

// 9. Media uploads: bytes must match the extension
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
assertTrue(MediaManagerService::bytesMatchExtension((string) $png, 'png'), 'Real PNG rejected');
assertTrue(!MediaManagerService::bytesMatchExtension((string) $png, 'jpg'), 'PNG accepted as .jpg');
assertTrue(!MediaManagerService::bytesMatchExtension('<?php echo 1; ?>', 'png'), 'PHP code accepted as .png');
assertTrue(!MediaManagerService::deleteMedia('../../../megabuilder.php'), 'deleteMedia escaped the uploads folder');
assertTrue(!MediaManagerService::deleteMedia('index.php'), 'deleteMedia removed the index guard');
echo "  [PASS] Media content checks and delete path confinement verified.\n";

echo "All PHP Core & Security Unit Tests PASSED (100%).\n";
