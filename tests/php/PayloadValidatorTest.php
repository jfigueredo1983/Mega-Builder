<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Validator\PayloadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayloadValidatorTest extends TestCase
{
    /** @return iterable<string, array{mixed, int}> */
    public static function clampCases(): iterable
    {
        yield 'negative'      => [-99999, 10];
        yield 'huge'          => [9999999, 120];
        yield 'NaN'           => [NAN, 16];
        yield 'INF'           => [INF, 16];
        yield '1e308 string'  => ['1e308', 120];
        yield 'non-numeric'   => ['not-a-number', 16];
        yield 'empty string'  => ['', 16];
        yield 'array'         => [[1], 16];
        yield 'in range'      => [42, 42];
    }

    #[DataProvider('clampCases')]
    public function testClampIntKeepsValuesInRange(mixed $input, int $expected): void
    {
        self::assertSame($expected, PayloadValidator::clampInt($input, 10, 120, 16));
    }

    public function testUnknownSectionIsRejected(): void
    {
        $res = PayloadValidator::validateTemplatePayload(['section_type' => 'evil_section']);
        self::assertFalse($res['ok']);
        self::assertNotEmpty($res['errors']);
    }

    public function testSubComponentSectionAlignsEntityKind(): void
    {
        $res = PayloadValidator::validateTemplatePayload(['entity_kind' => 'page_template', 'section_type' => 'slider']);
        self::assertTrue($res['ok']);
        self::assertSame('sub_component', $res['data']['entity_kind']);
    }

    public function testHookOutsideAllowlistFallsBackToSectionDefault(): void
    {
        $res = PayloadValidator::validateTemplatePayload(['section_type' => 'footer', 'hook_name' => 'actionDeleteEverything']);
        self::assertSame('displayFooter', $res['data']['hook_name']);
    }

    public function testScopeTargetsAreCleanPositiveIntegers(): void
    {
        $res = PayloadValidator::validateTemplatePayload(['scope_targets' => [3, '7', -1, 'x', 3, 0]]);
        self::assertSame([3, 7], $res['data']['scope_targets']);
    }

    public function testLayoutTreeLimitsAndIdFormats(): void
    {
        $sections = array_fill(0, 80, ['id' => '"><script>', 'columns' => [['widgets' => [['type' => 'Bad Type!']]]]]);
        $clean = PayloadValidator::sanitizeLayoutTree(['sections' => $sections]);

        self::assertCount(60, $clean['sections']);
        $sec = $clean['sections'][0];
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9_-]{2,48}$/', $sec['id']);
        self::assertSame('heading', $sec['columns'][0]['widgets'][0]['type']);
    }

    public function testWidgetContentIsSanitizedByKey(): void
    {
        $clean = PayloadValidator::sanitizeLayoutTree(['sections' => [[
            'columns' => [[
                'widgets' => [[
                    'type'    => 'button',
                    'content' => [
                        'text' => '<b onclick="x()">Buy</b>',
                        'url'  => 'javascript:alert(1)',
                        'html' => '<p onmouseover="x()">ok</p>',
                    ],
                ]],
            ]],
        ]]]);
        $c = $clean['sections'][0]['columns'][0]['widgets'][0]['content'];

        self::assertSame('Buy', $c['text']);
        self::assertSame('#', $c['url']);
        self::assertStringNotContainsString('onmouseover', $c['html']);
    }

    public function testGlobalSettingsDefaultsAndColorValidation(): void
    {
        $g = PayloadValidator::validateGlobalSettings(['colors' => ['primary' => 'red; } body { display:none']]);
        self::assertSame('#2563eb', $g['colors']['primary']);
        self::assertArrayHasKey('homepage', $g['page_rules']);
        self::assertTrue($g['page_rules']['homepage']['enabled']);
    }
}
