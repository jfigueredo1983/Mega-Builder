<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Security\CssSanitizer;
use MegaBuilder\Security\HtmlSanitizer;
use MegaBuilder\Security\SvgSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    private const XSS_CANARY = '"><svg onload="window.__qa_xss=1"><script>window.__qa_xss=1</script>Hello & Welcome {{mb:shop_name}}';

    public function testPlainTextStripsMarkupButKeepsDynamicTags(): void
    {
        $clean = HtmlSanitizer::sanitizePlainText(self::XSS_CANARY);
        self::assertStringNotContainsString('onload', $clean);
        self::assertStringNotContainsString('<script', $clean);
        self::assertStringContainsString('{{mb:shop_name}}', $clean);
    }

    public function testEscapeOutputNeverDoubleEscapes(): void
    {
        $once = HtmlSanitizer::escapeOutput('Coats & Jackets');
        self::assertSame('Coats &amp; Jackets', $once);
        self::assertSame($once, HtmlSanitizer::escapeOutput($once));
        self::assertSame('&lt;script&gt;', HtmlSanitizer::escapeOutput('&lt;script&gt;'));
    }

    public function testRichHtmlRemovesHandlersAndScriptUrls(): void
    {
        $clean = HtmlSanitizer::sanitizeRichHtml('<p onclick="alert(1)">Safe <a href="javascript:alert(1)">Link</a><svg onload="x()"></svg><script>alert(1)</script></p>');
        self::assertStringNotContainsString('onclick', $clean);
        self::assertStringNotContainsString('onload', $clean);
        self::assertStringNotContainsString('javascript:', $clean);
        self::assertStringNotContainsString('<script', $clean);
        self::assertStringContainsString('Safe', $clean);
    }

    public function testRichHtmlOnlyKeepsAllowlistedIframes(): void
    {
        self::assertSame('', HtmlSanitizer::sanitizeRichHtml('<iframe src="https://evil.example/x"></iframe>'));
        $yt = HtmlSanitizer::sanitizeRichHtml('<iframe src="https://www.youtube-nocookie.com/embed/abc" onload="x()"></iframe>');
        self::assertStringContainsString('sandbox=', $yt);
        self::assertStringNotContainsString('onload', $yt);
    }

    /** @return iterable<string, array{string, string}> */
    public static function urlCases(): iterable
    {
        yield 'protocol-relative'  => ['//evil.example/x', '#'];
        yield 'backslash host'     => ['/\\evil.example', '#'];
        yield 'javascript mixed'   => [' JaVaScRiPt:alert(1)', '#'];
        yield 'javascript entity'  => ['jav&#x09;ascript:alert(1)', '#'];
        yield 'data'               => ['data:text/html,x', '#'];
        yield 'ftp'                => ['ftp://x.example', '#'];
        yield 'same-site path'     => ['/2-home', '/2-home'];
        yield 'https'              => ['https://shop.example/a?b=1', 'https://shop.example/a?b=1'];
        yield 'mailto'             => ['mailto:hi@example.com', 'mailto:hi@example.com'];
        yield 'dynamic tag'        => ['{{mb:product_url}}', '{{mb:product_url}}'];
        yield 'anchor'             => ['#catalog', '#catalog'];
        yield 'empty'              => ['', '#'];
    }

    #[DataProvider('urlCases')]
    public function testSanitizeUrl(string $input, string $expected): void
    {
        self::assertSame($expected, HtmlSanitizer::sanitizeUrl($input));
    }

    public function testCssBlockCannotBreakOutOfStyleTag(): void
    {
        $clean = strtolower(CssSanitizer::sanitizeCssBlock('.a { color: red; }</style><script>alert(1)</script><style>@import url(x); .b{background:url(javascript:alert(1))} .c{width:expression(alert(1))}'));
        self::assertStringNotContainsString('</style', $clean);
        self::assertStringNotContainsString('<script', $clean);
        self::assertStringNotContainsString('@import', $clean);
        self::assertStringNotContainsString('javascript', $clean);
        self::assertStringNotContainsString('expression(', $clean);
    }

    public function testColorAllowlist(): void
    {
        self::assertSame('#ff0000', CssSanitizer::sanitizeColor('#ff0000'));
        self::assertSame('rgba(0, 0, 0, 0.5)', CssSanitizer::sanitizeColor('rgba(0, 0, 0, 0.5)'));
        self::assertSame('#1e293b', CssSanitizer::sanitizeColor('red;}body{display:none'));
    }

    public function testSvgSanitizerRemovesActiveContent(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="x()"><script>alert(1)</script><foreignObject><iframe></iframe></foreignObject><a href="javascript:alert(1)"><path d="M0 0h10v10z" onclick="evil()"/></a></svg>';
        $clean = SvgSanitizer::sanitizeSvg($svg);

        self::assertIsString($clean);
        self::assertStringContainsString('<path', $clean);
        foreach (['onload', 'onclick', '<script', 'foreignobject', 'javascript:'] as $bad) {
            self::assertStringNotContainsString($bad, strtolower($clean));
        }
    }

    public function testSvgSanitizerRejectsEntitiesAndNonSvg(): void
    {
        self::assertNull(SvgSanitizer::sanitizeSvg('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>'));
        self::assertNull(SvgSanitizer::sanitizeSvg('<html><body>hi</body></html>'));
    }
}
