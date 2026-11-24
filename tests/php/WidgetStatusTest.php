<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Compiler\LayoutCompiler;
use MegaBuilder\Compiler\WidgetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every widget is either "ready" (real storefront output) or "coming_soon" (renders nothing),
 * never a placeholder block that looks finished in the studio but is fake on the storefront.
 */
final class WidgetStatusTest extends TestCase
{
    /** @param array<string, mixed> $content */
    private static function compileOne(string $type, array $content): string
    {
        $layout = ['sections' => [['id' => 'sec_t', 'columns' => [['id' => 'col_t', 'widgets' => [[
            'id' => 'w_t', 'type' => $type, 'content' => $content,
        ]]]]]]];
        return LayoutCompiler::compile($layout)['html'];
    }

    public function testEveryWidgetHasAStatusAndTheSplitIsKnown(): void
    {
        $byStatus = ['ready' => 0, 'coming_soon' => 0];
        foreach (WidgetRegistry::getAllWidgets() as $w) {
            self::assertArrayHasKey('status', $w, $w['type']);
            $byStatus[$w['status']]++;
        }
        self::assertSame(['ready' => 45, 'coming_soon' => 32], $byStatus);
    }

    public function testRenderedTypesAreAllRegisteredWidgets(): void
    {
        $registered = array_column(WidgetRegistry::getAllWidgets(), 'type');
        self::assertSame([], array_values(array_diff(LayoutCompiler::RENDERED_TYPES, $registered)), 'Renderer listed for an unknown widget type');
    }

    /** @return iterable<string, array{string, array<string, mixed>, string}> */
    public static function widgets(): iterable
    {
        foreach (WidgetRegistry::getAllWidgets() as $w) {
            yield $w['type'] => [$w['type'], $w['defaults'], $w['status']];
        }
    }

    /** @param array<string, mixed> $defaults */
    #[DataProvider('widgets')]
    public function testReadyWidgetsRenderRealMarkupAndComingSoonRenderNothing(string $type, array $defaults, string $status): void
    {
        $html = self::compileOne($type, $defaults);
        $marker = 'data-mb-widget-type="' . $type . '"';

        if ($status === 'ready') {
            self::assertStringContainsString($marker, $html);
            self::assertStringNotContainsString('mb-generic-block', $html, "{$type} fell back to the placeholder block");
        } else {
            self::assertStringNotContainsString($marker, $html, "{$type} is coming soon but still rendered on the storefront");
        }
    }

    public function testDividerRendersAndClampsInput(): void
    {
        $html = self::compileOne('divider', ['style_type' => 'dashed', 'thickness' => 3]);
        self::assertStringContainsString('<hr class="mb-divider"', $html);
        self::assertStringContainsString('3px dashed', $html);

        $html = self::compileOne('divider', ['style_type' => 'url(javascript:x)', 'thickness' => 999]);
        self::assertStringContainsString('20px solid', $html);
        self::assertStringNotContainsString('javascript', $html);
    }

    public function testSpacerRendersAndClampsHeight(): void
    {
        self::assertStringContainsString('class="mb-spacer" style="height:48px"', self::compileOne('spacer', ['height_px' => 48]));
        self::assertStringContainsString('style="height:400px"', self::compileOne('spacer', ['height_px' => 100000]));
        self::assertStringContainsString('style="height:0px"', self::compileOne('spacer', ['height_px' => -5]));
    }

    public function testComingSoonWidgetsAddNoCss(): void
    {
        $layout = ['sections' => [['id' => 'sec_t', 'columns' => [['id' => 'col_t', 'widgets' => [['id' => 'w_soon', 'type' => 'breadcrumb']]]]]]];
        self::assertStringNotContainsString('.mb-w-w_soon', LayoutCompiler::compile($layout)['css']);
    }
}
