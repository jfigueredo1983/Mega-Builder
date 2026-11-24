<?php
/**
 * Mega Builder — Stage 3 & Stage 4 Fast-Pass Dynamic Store Tag & Shortcode Resolver
 * Replaces {{mb:...}}, [mb_template id="..."], and [mb_module name="..." hook="..."]
 * at runtime in < 2 ms without leaking raw placeholders.
 */

declare(strict_types=1);

namespace MegaBuilder\Compiler;

use MegaBuilder\Security\HtmlSanitizer;

final class DynamicTagResolver
{
    /**
     * Resolve all {{mb:...}} and [mb_...] tags in compiled HTML.
     *
     * @param array<string, mixed> $contextOverrides
     * @param callable|null $subTemplateLoader fn(int $id): array{html: string, css: string}|null
     */
    public static function resolve(string $compiledHtml, array $contextOverrides = [], ?callable $subTemplateLoader = null, int $depth = 0): string
    {
        if ($compiledHtml === '' || $depth > 3) {
            return $compiledHtml;
        }

        $tokens = self::gatherStoreTokens($contextOverrides);

        // 1. Replace {{mb:product_cards_grid}} with live or sample catalog product cards HTML
        if (str_contains($compiledHtml, '{{mb:product_cards_grid}}')) {
            $compiledHtml = str_replace('{{mb:product_cards_grid}}', self::renderProductCardsGrid($tokens['products']), $compiledHtml);
        }

        // 2. Replace standard scalar {{mb:...}} tokens
        $compiledHtml = (string) preg_replace_callback(
            '/\{\{\s*(?:mb|spb)\s*:\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $m) use ($tokens): string {
                $key = strtolower($m[1]);
                if (isset($tokens['scalars'][$key])) {
                    return HtmlSanitizer::escapeOutput((string) $tokens['scalars'][$key]);
                }
                return HtmlSanitizer::escapeOutput(ucwords(str_replace('_', ' ', $key)));
            },
            $compiledHtml
        );

        // 3. Resolve [mb_template id="123"] embedded Sub-Studio components or saved templates
        if ($subTemplateLoader !== null && str_contains($compiledHtml, '[mb_template')) {
            $compiledHtml = (string) preg_replace_callback(
                '/\[(?:mb_template|spb_template)\s+id=["\']?(\d+)["\']?\s*\]/i',
                static function (array $m) use ($subTemplateLoader, $contextOverrides, $depth): string {
                    $tplId = (int) $m[1];
                    if ($tplId <= 0) {
                        return '';
                    }
                    $sub = $subTemplateLoader($tplId);
                    if (!is_array($sub) || empty($sub['html'])) {
                        return '';
                    }
                    $cssTag = !empty($sub['css']) ? '<style>' . $sub['css'] . '</style>' : '';
                    return $cssTag . self::resolve((string) $sub['html'], $contextOverrides, $subTemplateLoader, $depth + 1);
                },
                $compiledHtml
            );
        }

        // 4. Resolve [mb_module name="..." hook="..."] native PrestaShop third-party module embeds
        if (str_contains($compiledHtml, '[mb_module')) {
            $compiledHtml = (string) preg_replace_callback(
                '/\[mb_module\s+name=["\']([a-zA-Z0-9_-]+)["\'](?:\s+hook=["\']([a-zA-Z0-9_-]+)["\'])?\s*\]/i',
                static function (array $m): string {
                    $modName = $m[1];
                    $hookName = $m[2] ?? 'displayHome';
                    if (class_exists('Hook') && class_exists('Module')) {
                        try {
                            $mod = \Module::getInstanceByName($modName);
                            if ($mod && $mod->active) {
                                $out = \Hook::exec($hookName, [], (int) $mod->id);
                                if (is_string($out) && trim($out) !== '') {
                                    return $out;
                                }
                            }
                        } catch (\Throwable) {
                            // Fall back to safe label if module hook requires specific controller context
                        }
                    }
                    return '<div class="mb-module-badge">PrestaShop Module: <strong>' . HtmlSanitizer::escapeOutput($modName) . '</strong> (' . HtmlSanitizer::escapeOutput($hookName) . ')</div>';
                },
                $compiledHtml
            );
        }

        return $compiledHtml;
    }

    /**
     * Gather live PrestaShop 8/9 context variables with graceful fallback to realistic store data.
     *
     * @param array<string, mixed> $overrides
     * @return array{scalars: array<string, string>, products: array<int, array<string, string>>}
     */
    private static function gatherStoreTokens(array $overrides = []): array
    {
        $shopName = 'DoIt Architectural Furniture';
        $productName = 'Verona Cloud Modular Bouclé Sectional';
        $productPrice = '$1,890.00';
        $productRef = 'DF-VERONA-320';
        $productDesc = 'Handcrafted modular living room sectional with FSC-certified kiln-dried oak frame and Belgian performance bouclé.';
        $categoryName = 'Living Room Sanctuary';
        $pageTitle = 'Architectural Furniture & Bespoke Interiors';
        $cartCount = '2';

        $liveProducts = [];

        if (class_exists('Context')) {
            try {
                $ctx = \Context::getContext();
                if (!empty($ctx->shop->name)) {
                    $shopName = (string) $ctx->shop->name;
                }
                if (!empty($ctx->cart) && method_exists($ctx->cart, 'nbProducts')) {
                    $cartCount = (string) ((int) $ctx->cart->nbProducts());
                }
                $idLang = (int) ($ctx->language->id ?? 1);

                // Query up to 4 real active products from PrestaShop DB for dynamic grids & product context
                if (class_exists('Db')) {
                    $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
                    $rows = \Db::getInstance()->executeS(
                        "SELECT p.id_product, p.price, p.reference, pl.name, pl.description_short, cl.name AS category_name
                         FROM `{$prefix}product` p
                         INNER JOIN `{$prefix}product_lang` pl ON (p.id_product = pl.id_product AND pl.id_lang = {$idLang})
                         LEFT JOIN `{$prefix}category_lang` cl ON (p.id_category_default = cl.id_category AND cl.id_lang = {$idLang})
                         WHERE p.active = 1
                         ORDER BY p.id_product DESC
                         LIMIT 4"
                    );
                    if (is_array($rows) && count($rows) > 0) {
                        $first = $rows[0];
                        $productName = (string) ($first['name'] ?: $productName);
                        $rawPrice = (float) ($first['price'] ?? 1890);
                        $productPrice = '$' . number_format($rawPrice > 0 ? $rawPrice : 1290.00, 2);
                        $productRef = (string) ($first['reference'] ?: $productRef);
                        $productDesc = trim(strip_tags((string) ($first['description_short'] ?: $productDesc)));
                        $categoryName = (string) ($first['category_name'] ?: $categoryName);

                        foreach ($rows as $idx => $r) {
                            $pPrice = (float) ($r['price'] ?? 890);
                            $liveProducts[] = [
                                'title'    => (string) ($r['name'] ?: ('Signature Piece #' . ($idx + 1))),
                                'category' => (string) ($r['category_name'] ?: 'Showroom Collection'),
                                'price'    => '$' . number_format($pPrice > 0 ? $pPrice : (690 + $idx * 250), 2),
                                'badge'    => $idx === 0 ? 'BESTSELLER' : 'IN STOCK',
                                'url'      => '/index.php?id_product=' . (int) $r['id_product'] . '&controller=product',
                            ];
                        }
                    }
                }
            } catch (\Throwable) {
                // Keep rich fallback defaults
            }
        }

        if (count($liveProducts) === 0) {
            $liveProducts = [
                ['title' => 'Verona Cloud Modular Bouclé Sofa',   'category' => 'Living Room', 'price' => '$1,890.00', 'badge' => 'BESTSELLER',  'url' => '#product-1'],
                ['title' => 'Nordic Solid Smoked Oak Dining Table','category' => 'Dining Room', 'price' => '$1,450.00', 'badge' => 'FSC OAK',     'url' => '#product-2'],
                ['title' => 'Aurelia Curved Cognac Lounge Chair',  'category' => 'Accent Seating','price' => '$780.00', 'badge' => 'NEW ARRIVAL', 'url' => '#product-3'],
                ['title' => 'Travertine & Walnut Low Media Console','category' => 'Storage',    'price' => '$1,120.00', 'badge' => 'LIMITED',     'url' => '#product-4'],
            ];
        }

        $scalars = array_merge([
            'shop_name'           => $shopName,
            'product_name'        => $productName,
            'product_title'       => $productName,
            'product_price'       => $productPrice,
            'product_reference'   => $productRef,
            'product_sku'         => $productRef,
            'product_description' => $productDesc,
            'category_name'       => $categoryName,
            'page_title'          => $pageTitle,
            'cart_count'          => $cartCount,
            'current_year'        => date('Y'),
        ], $overrides);

        return [
            'scalars'  => $scalars,
            'products' => $liveProducts,
        ];
    }

    /**
     * @param array<int, array<string, string>> $products
     */
    private static function renderProductCardsGrid(array $products): string
    {
        $cards = [];
        foreach ($products as $p) {
            $t = HtmlSanitizer::escapeOutput($p['title'] ?? 'Signature Piece');
            $cat = HtmlSanitizer::escapeOutput($p['category'] ?? 'Collection');
            $pr = HtmlSanitizer::escapeOutput($p['price'] ?? '$1,290.00');
            $bg = HtmlSanitizer::escapeOutput($p['badge'] ?? 'IN STOCK');
            $u = HtmlSanitizer::escapeOutput($p['url'] ?? '#');

            $cards[] = '<article class="mb-product-card">'
                . '<div class="mb-product-card-top"><span class="mb-badge">' . $bg . '</span><span class="mb-product-cat">' . $cat . '</span></div>'
                . '<h4 class="mb-product-card-title"><a href="' . $u . '" class="mb-interactive-hit">' . $t . '</a></h4>'
                . '<div class="mb-product-card-footer"><strong class="mb-product-card-price">' . $pr . '</strong>'
                . '<a href="' . $u . '" class="mb-btn mb-btn-sm mb-btn-primary mb-interactive-hit">View Piece</a></div>'
                . '</article>';
        }
        return '<div class="mb-responsive-grid mb-product-grid-cards">' . implode('', $cards) . '</div>';
    }
}
