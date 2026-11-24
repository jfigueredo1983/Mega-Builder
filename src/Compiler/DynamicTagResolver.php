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

        // 4. Resolve [mb_category_menu ...] MegaMenus built from the live store category tree.
        if (str_contains($compiledHtml, '[mb_category_menu')) {
            $compiledHtml = (string) preg_replace_callback(
                '/\[mb_category_menu id="([a-zA-Z0-9_-]+)" max="(\d+)" collapse="(\d+)" label="([A-Za-z0-9+\/=]*)"\]/',
                static function (array $m): string {
                    $ctx = class_exists('Context') ? \Context::getContext() : null;
                    $items = MegaMenuRenderer::loadCategoryItems(
                        (int) ($ctx->language->id ?? 1),
                        (int) ($ctx->shop->id ?? 1),
                        (int) $m[2]
                    );
                    $label = (string) base64_decode($m[4], true);
                    return MegaMenuRenderer::render($items, $m[1], $label !== '' ? $label : 'Menu', (int) $m[3]);
                },
                $compiledHtml
            );
        }

        // 5. Resolve [mb_module name="..." hook="..."] native PrestaShop third-party module embeds
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
     * Gather store values for the current request.
     *
     * On a product page the product_* tags describe the product being viewed; on a category page
     * category_name is that category and the product grid lists its products. Elsewhere the grid shows
     * the newest products visible in this shop. Prices include tax when the customer group sees
     * tax-incl prices, and are formatted in the visitor's currency. Outside PrestaShop (unit tests)
     * neutral sample values are used.
     *
     * @param array<string, mixed> $overrides
     * @return array{scalars: array<string, string>, products: array<int, array<string, string>>}
     */
    private static function gatherStoreTokens(array $overrides = []): array
    {
        $scalars = [
            'shop_name'           => 'Sample Store',
            'product_name'        => 'Sample Product',
            'product_price'       => '0.00',
            'product_reference'   => '',
            'product_description' => '',
            'product_url'         => '#',
            'category_name'       => 'Sample Category',
            'page_title'          => '',
            'cart_count'          => '0',
            'current_year'        => date('Y'),
        ];
        $products = [];

        if (class_exists('Context')) {
            try {
                $ctx = \Context::getContext();
                $idLang = (int) ($ctx->language->id ?? 1);
                $idShop = (int) ($ctx->shop->id ?? 1);

                if (!empty($ctx->shop->name)) {
                    $scalars['shop_name'] = (string) $ctx->shop->name;
                }
                if (!empty($ctx->cart) && method_exists($ctx->cart, 'nbProducts')) {
                    $scalars['cart_count'] = (string) ((int) $ctx->cart->nbProducts());
                }

                $controller = $ctx->controller ?? null;
                $currentProduct = ($controller && method_exists($controller, 'getProduct')) ? $controller->getProduct() : null;
                $currentCategory = ($controller && method_exists($controller, 'getCategory')) ? $controller->getCategory() : null;

                if ($currentProduct instanceof \Product && \Validate::isLoadedObject($currentProduct)) {
                    $name = is_array($currentProduct->name) ? (string) ($currentProduct->name[$idLang] ?? '') : (string) $currentProduct->name;
                    $desc = is_array($currentProduct->description_short) ? (string) ($currentProduct->description_short[$idLang] ?? '') : (string) $currentProduct->description_short;
                    $scalars['product_name'] = $name;
                    $scalars['product_reference'] = (string) $currentProduct->reference;
                    $scalars['product_description'] = trim(strip_tags($desc));
                    $scalars['product_price'] = self::formatPrice(self::productPrice((int) $currentProduct->id));
                    $scalars['product_url'] = (string) $ctx->link->getProductLink($currentProduct);
                    $scalars['page_title'] = $name;
                    $idCat = (int) $currentProduct->id_category_default;
                    if ($idCat > 0) {
                        $cat = new \Category($idCat, $idLang);
                        $scalars['category_name'] = (string) $cat->name;
                    }
                }

                $gridCategoryId = 0;
                if ($currentCategory instanceof \Category && \Validate::isLoadedObject($currentCategory)) {
                    $catName = is_array($currentCategory->name) ? (string) ($currentCategory->name[$idLang] ?? '') : (string) $currentCategory->name;
                    $scalars['category_name'] = $catName;
                    $scalars['page_title'] = $catName;
                    $gridCategoryId = (int) $currentCategory->id;
                }

                $products = self::loadGridProducts($idLang, $idShop, $gridCategoryId, 4);

                // Off product pages, product_* tags fall back to the first grid product.
                if (!($currentProduct instanceof \Product) && isset($products[0])) {
                    $scalars['product_name'] = $products[0]['title'];
                    $scalars['product_price'] = $products[0]['price'];
                    $scalars['product_url'] = $products[0]['url'];
                }
            } catch (\Throwable) {
                // Keep neutral defaults if any core API is unavailable in this context.
            }
        }

        if (count($products) === 0) {
            $products = [
                ['title' => 'Sample Product 1', 'category' => 'Sample Category', 'price' => '0.00', 'badge' => '', 'url' => '#'],
                ['title' => 'Sample Product 2', 'category' => 'Sample Category', 'price' => '0.00', 'badge' => '', 'url' => '#'],
            ];
        }

        $scalars['product_title'] = $scalars['product_name'];
        $scalars['product_sku'] = $scalars['product_reference'];

        return [
            'scalars'  => array_merge($scalars, array_map('strval', array_filter($overrides, 'is_scalar'))),
            'products' => $products,
        ];
    }

    /**
     * Products visible in this shop: from one category when given, else the newest.
     *
     * @return array<int, array<string, string>>
     */
    private static function loadGridProducts(int $idLang, int $idShop, int $idCategory, int $limit): array
    {
        if (!class_exists('Db')) {
            return [];
        }
        $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $catJoin = $idCategory > 0
            ? "INNER JOIN `{$prefix}category_product` cp ON (cp.id_product = p.id_product AND cp.id_category = " . $idCategory . ")"
            : '';
        $order = $idCategory > 0 ? 'cp.position ASC' : 'p.id_product DESC';

        $rows = \Db::getInstance()->executeS(
            "SELECT p.id_product, pl.name, pl.link_rewrite, cl.name AS category_name
             FROM `{$prefix}product` p
             INNER JOIN `{$prefix}product_shop` ps ON (ps.id_product = p.id_product AND ps.id_shop = {$idShop} AND ps.active = 1 AND ps.visibility IN ('both', 'catalog'))
             INNER JOIN `{$prefix}product_lang` pl ON (pl.id_product = p.id_product AND pl.id_lang = {$idLang} AND pl.id_shop = {$idShop})
             LEFT JOIN `{$prefix}category_lang` cl ON (cl.id_category = ps.id_category_default AND cl.id_lang = {$idLang} AND cl.id_shop = {$idShop})
             {$catJoin}
             ORDER BY {$order}
             LIMIT " . max(1, $limit)
        );
        if (!is_array($rows)) {
            return [];
        }

        $link = \Context::getContext()->link;
        $out = [];
        foreach ($rows as $r) {
            $idProduct = (int) $r['id_product'];
            $out[] = [
                'title'    => (string) $r['name'],
                'category' => (string) ($r['category_name'] ?? ''),
                'price'    => self::formatPrice(self::productPrice($idProduct)),
                'badge'    => '',
                'url'      => (string) $link->getProductLink($idProduct, (string) $r['link_rewrite']),
            ];
        }
        return $out;
    }

    /**
     * Final price the current visitor would pay (respects tax display, specific prices and group reductions).
     */
    private static function productPrice(int $idProduct): float
    {
        $taxIncl = true;
        if (class_exists('Product') && method_exists('Product', 'getTaxCalculationMethod')) {
            $taxIncl = \Product::getTaxCalculationMethod() !== 1; // PS_TAX_EXC === 1
        }
        return (float) \Product::getPriceStatic($idProduct, $taxIncl);
    }

    /**
     * Format an amount in the visitor's currency and locale.
     */
    private static function formatPrice(float $amount): string
    {
        try {
            $ctx = \Context::getContext();
            if (method_exists($ctx, 'getCurrentLocale') && $ctx->getCurrentLocale() && !empty($ctx->currency->iso_code)) {
                return (string) $ctx->getCurrentLocale()->formatPrice($amount, (string) $ctx->currency->iso_code);
            }
        } catch (\Throwable) {
            // fall through
        }
        return number_format($amount, 2);
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
            $bg = HtmlSanitizer::escapeOutput($p['badge'] ?? '');
            $u = HtmlSanitizer::escapeOutput($p['url'] ?? '#');

            $cards[] = '<article class="mb-product-card">'
                . '<div class="mb-product-card-top">' . ($bg !== '' ? '<span class="mb-badge">' . $bg . '</span>' : '') . '<span class="mb-product-cat">' . $cat . '</span></div>'
                . '<h4 class="mb-product-card-title"><a href="' . $u . '" class="mb-interactive-hit">' . $t . '</a></h4>'
                . '<div class="mb-product-card-footer"><strong class="mb-product-card-price">' . $pr . '</strong>'
                . '<a href="' . $u . '" class="mb-btn mb-btn-sm mb-btn-primary mb-interactive-hit">View Piece</a></div>'
                . '</article>';
        }
        return '<div class="mb-responsive-grid mb-product-grid-cards">' . implode('', $cards) . '</div>';
    }
}
