<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Compiler\DynamicTagResolver;
use MegaBuilder\Compiler\LayoutCompiler;
use MegaBuilder\Compiler\MegaMenuRenderer;
use PHPUnit\Framework\TestCase;

final class MegaMenuTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private static function sampleItems(): array
    {
        return [
            ['title' => 'Living', 'url' => '/living', 'badge' => 'NEW', 'columns' => [
                ['heading' => 'Seating', 'url' => '/seating', 'links' => [['title' => 'Sofas', 'url' => '/sofas'], ['title' => 'Chairs', 'url' => '']]],
            ], 'promo_title' => 'Sale', 'promo_image' => '#', 'promo_url' => '/sale'],
            ['title' => 'Contact', 'url' => '/contact', 'columns' => []],
        ];
    }

    /** @param array<string, mixed> $content */
    private static function compileMenu(array $content, array $global = []): string
    {
        $layout = ['sections' => [['id' => 'sec_m', 'columns' => [['id' => 'col_m', 'widgets' => [[
            'id' => 'w_menu1', 'type' => 'nav_megamenu', 'content' => $content,
        ]]]]]]];
        return LayoutCompiler::compile($layout, $global)['html'];
    }

    public function testNormalizeAcceptsOneDotZeroAnswerFormat(): void
    {
        $items = MegaMenuRenderer::normalizeItems([['title' => 'Living', 'url' => '/x', 'answer' => 'Sofas • Tables • Chairs']]);
        self::assertCount(1, $items);
        self::assertSame(['Sofas', 'Tables', 'Chairs'], array_column($items[0]['columns'][0]['links'], 'title'));
        self::assertSame('', $items[0]['columns'][0]['links'][0]['url']);
    }

    public function testNormalizeDropsEmptyEntriesCapsCountsAndTreatsHashImageAsNone(): void
    {
        $many = array_fill(0, 30, ['title' => 'X', 'columns' => array_fill(0, 9, ['heading' => 'H', 'links' => array_fill(0, 40, ['title' => 'L'])])]);
        $many[] = ['title' => '   '];
        $items = MegaMenuRenderer::normalizeItems($many);

        self::assertCount(MegaMenuRenderer::MAX_ITEMS, $items);
        self::assertCount(MegaMenuRenderer::MAX_COLUMNS, $items[0]['columns']);
        self::assertCount(MegaMenuRenderer::MAX_LINKS, $items[0]['columns'][0]['links']);
        self::assertSame('', MegaMenuRenderer::normalizeItems(self::sampleItems())[0]['promo_image']);
        self::assertSame([], MegaMenuRenderer::normalizeItems('nope'));
    }

    public function testCategoriesBecomeItemsColumnsAndLinks(): void
    {
        $tree = [
            ['id_category' => 3, 'name' => 'Clothes', 'link_rewrite' => 'clothes', 'children' => [
                ['id_category' => 4, 'name' => 'Men', 'link_rewrite' => 'men', 'children' => [
                    ['id_category' => 9, 'name' => 'Shirts', 'link_rewrite' => 'shirts'],
                ]],
            ]],
            ['id_category' => 6, 'name' => 'Accessories', 'link_rewrite' => 'accessories'],
            ['id_category' => 7, 'name' => 'Art', 'link_rewrite' => 'art'],
        ];
        $items = MegaMenuRenderer::categoriesToItems($tree, static fn(int $id, string $r): string => "/{$id}-{$r}", 2);

        self::assertSame(['Clothes', 'Accessories'], array_column($items, 'title'));
        self::assertSame('/3-clothes', $items[0]['url']);
        self::assertSame('Men', $items[0]['columns'][0]['heading']);
        self::assertSame('/4-men', $items[0]['columns'][0]['url']);
        self::assertSame([['title' => 'Shirts', 'url' => '/9-shirts']], $items[0]['columns'][0]['links']);
        self::assertSame([], $items[1]['columns']);
    }

    public function testRenderIsAccessibleAndOnlyItemsWithContentGetPanels(): void
    {
        $html = MegaMenuRenderer::render(MegaMenuRenderer::normalizeItems(self::sampleItems()), 'mbmm-x', 'Main menu', 1024);

        self::assertStringContainsString('<nav class="mb-mm" id="mbmm-x" aria-label="Main menu" data-mb-component="megamenu" data-mb-collapse="1024">', $html);
        self::assertStringContainsString('aria-controls="mbmm-x-list"', $html);
        self::assertSame(1, substr_count($html, 'class="mb-mm-toggle'), 'Only "Living" has a panel');
        self::assertStringContainsString('aria-controls="mbmm-x-p0"', $html);
        self::assertStringContainsString('id="mbmm-x-p0"', $html);
        self::assertStringNotContainsString('mbmm-x-p1', $html);
        self::assertStringContainsString('<span class="mb-mm-badge">NEW</span>', $html);
        self::assertStringContainsString('<span>Chairs</span>', $html, 'Link without URL is plain text');
        self::assertStringContainsString('<a class="mb-mm-promo" href="/sale"><span>Sale</span></a>', $html);

        // Every aria-controls target exists.
        preg_match_all('/aria-controls="([^"]+)"/', $html, $m);
        foreach ($m[1] as $target) {
            self::assertStringContainsString('id="' . $target . '"', $html);
        }
    }

    public function testRenderEscapesTextAndNeutralisesScriptUrls(): void
    {
        $html = MegaMenuRenderer::render(
            MegaMenuRenderer::normalizeItems([['title' => '<img src=x onerror=alert(1)>', 'url' => 'javascript:alert(1)']]),
            'mbmm-"><script>',
            'Menu "x"',
            1024
        );
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('aria-label="Menu &quot;x&quot;"', $html);
    }

    public function testRenderWithoutItemsIsEmpty(): void
    {
        self::assertSame('', MegaMenuRenderer::render([], 'mbmm-x', 'Menu', 1024));
    }

    public function testCompiledManualMenuUsesTabletBreakpointAndWidgetId(): void
    {
        $html = self::compileMenu(['source' => 'manual', 'menu_label' => 'Shop', 'items' => self::sampleItems()], ['layout' => ['tablet_breakpoint_px' => 900]]);
        self::assertStringContainsString('id="mbmm-w_menu1"', $html);
        self::assertStringContainsString('data-mb-collapse="900"', $html);
        self::assertStringContainsString('aria-label="Shop"', $html);
        self::assertStringContainsString('href="/sofas"', $html);
    }

    public function testSavedMenuSurvivesThePayloadSanitizer(): void
    {
        // Compile runs the save-time sanitizer first; nested columns/links must come through intact.
        $html = self::compileMenu(['source' => 'manual', 'items' => [[
            'title' => 'Living', 'url' => 'javascript:alert(1)', 'columns' => [
                ['heading' => 'Seating <b>x</b>', 'links' => [['title' => 'Sofas', 'url' => '//evil.example']]],
            ],
        ]]]);
        self::assertStringContainsString('Seating x', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('evil.example', $html);
    }

    public function testCategoryMenuIsResolvedAtRenderTime(): void
    {
        $html = self::compileMenu(['source' => 'categories', 'max_items' => 5, 'menu_label' => 'Shop by department']);
        self::assertMatchesRegularExpression('/\[mb_category_menu id="mbmm-w_menu1" max="5" collapse="1024" label="[A-Za-z0-9+\/=]+"\]/', $html);

        // Outside PrestaShop there are no categories: the placeholder resolves to nothing, never leaks.
        $resolved = DynamicTagResolver::resolve($html);
        self::assertStringNotContainsString('[mb_category_menu', $resolved);
        self::assertStringNotContainsString('mb-mm-bar', $resolved);
    }
}
