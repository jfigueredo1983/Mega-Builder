<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Compiler\DynamicTagResolver;
use MegaBuilder\Compiler\LayoutCompiler;
use MegaBuilder\Compiler\WidgetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompilerTest extends TestCase
{
    public function testRegistryCounts(): void
    {
        self::assertCount(77, WidgetRegistry::getAllWidgets());
        self::assertCount(19, WidgetRegistry::getStarterBlueprints());
    }

    public function testWidgetTypesAreUnique(): void
    {
        $types = array_column(WidgetRegistry::getAllWidgets(), 'type');
        self::assertSame(count($types), count(array_unique($types)));
    }

    /** @return iterable<string, array{string}> */
    public static function blueprintKeys(): iterable
    {
        foreach (array_keys(WidgetRegistry::getStarterBlueprints()) as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('blueprintKeys')]
    public function testBlueprintCompilesForAllViewportsAndResolvesTags(string $key): void
    {
        $bp = WidgetRegistry::getStarterBlueprints()[$key];
        $compiled = LayoutCompiler::compile($bp['layout']);

        self::assertNotSame('', $compiled['html']);
        self::assertStringContainsString('@media (max-width: 1024px)', $compiled['css']);
        self::assertStringContainsString('@media (max-width: 767px)', $compiled['css']);
        self::assertStringNotContainsString('</style', strtolower($compiled['css']));

        $resolved = DynamicTagResolver::resolve($compiled['html']);
        self::assertStringNotContainsString('{{mb:', $resolved);
    }

    public function testBreakpointsFollowGlobalSettings(): void
    {
        $bp = WidgetRegistry::getStarterBlueprints()['homepage'];
        $css = LayoutCompiler::compile($bp['layout'], ['layout' => ['tablet_breakpoint_px' => 900, 'mobile_breakpoint_px' => 600]])['css'];
        self::assertStringContainsString('@media (max-width: 900px)', $css);
        self::assertStringContainsString('@media (max-width: 600px)', $css);
    }

    public function testUserTextIsEscapedInCompiledHtml(): void
    {
        $layout = ['sections' => [['columns' => [['widgets' => [[
            'type'    => 'heading',
            'content' => ['text' => 'Tom & Jerry "quoted"'],
        ]]]]]]];
        $html = LayoutCompiler::compile($layout)['html'];
        self::assertStringContainsString('Tom &amp; Jerry &quot;quoted&quot;', $html);
    }

    public function testEmbeddedTemplateResolvesThroughLoaderWithDepthLimit(): void
    {
        $calls = 0;
        $loader = static function (int $id) use (&$calls): array {
            $calls++;
            // Every template embeds itself: recursion must stop.
            return ['html' => '<p>inner ' . $id . '</p>[mb_template id="' . $id . '"]', 'css' => '.x{}'];
        };
        $out = DynamicTagResolver::resolve('[mb_template id="7"]', [], $loader);

        self::assertStringContainsString('inner 7', $out);
        self::assertLessThanOrEqual(4, $calls);
    }

    public function testUnknownScalarTagIsReplacedNotLeaked(): void
    {
        $out = DynamicTagResolver::resolve('<p>{{mb:some_unknown_tag}}</p>');
        self::assertStringNotContainsString('{{', $out);
    }
}
