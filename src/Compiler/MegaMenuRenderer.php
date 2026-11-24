<?php
/**
 * Mega Builder — MegaMenu storefront renderer.
 *
 * One menu = top-level items; each item can open a panel of columns (heading + links) and an
 * optional promo card. Items come from the widget's own content ("manual") or, at render time,
 * from the store's category tree ("categories").
 */

declare(strict_types=1);

namespace MegaBuilder\Compiler;

use MegaBuilder\Security\HtmlSanitizer;

final class MegaMenuRenderer
{
    public const MAX_ITEMS = 12;
    public const MAX_COLUMNS = 4;
    public const MAX_LINKS = 12;

    /**
     * Normalise widget content items into the menu shape, accepting the 1.0 format
     * (`answer: "Sofas • Tables"`) as a single column of plain entries.
     *
     * @param mixed $items
     * @return array<int, array{title: string, url: string, badge: string, columns: array<int, array{heading: string, url: string, links: array<int, array{title: string, url: string}>}>, promo_image: string, promo_title: string, promo_url: string}>
     */
    public static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = self::str($item['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $columns = [];
            foreach (array_slice(is_array($item['columns'] ?? null) ? $item['columns'] : [], 0, self::MAX_COLUMNS) as $col) {
                if (!is_array($col)) {
                    continue;
                }
                $links = [];
                foreach (array_slice(is_array($col['links'] ?? null) ? $col['links'] : [], 0, self::MAX_LINKS) as $link) {
                    if (is_array($link) && self::str($link['title'] ?? '') !== '') {
                        $links[] = ['title' => self::str($link['title']), 'url' => self::str($link['url'] ?? '')];
                    }
                }
                $heading = self::str($col['heading'] ?? '');
                if ($heading !== '' || $links !== []) {
                    $columns[] = ['heading' => $heading, 'url' => self::str($col['url'] ?? ''), 'links' => $links];
                }
            }

            // 1.0 content: "A • B • C" becomes one column of entries without their own links.
            if ($columns === [] && self::str($item['answer'] ?? '') !== '') {
                $entries = array_filter(array_map('trim', preg_split('/\s*[•|,]\s*/u', self::str($item['answer'])) ?: []));
                $columns[] = [
                    'heading' => '',
                    'url'     => '',
                    'links'   => array_map(static fn(string $t): array => ['title' => $t, 'url' => ''], array_slice(array_values($entries), 0, self::MAX_LINKS)),
                ];
            }

            $out[] = [
                'title'       => $title,
                'url'         => self::str($item['url'] ?? ''),
                'badge'       => self::str($item['badge'] ?? ''),
                'columns'     => $columns,
                // The save-time URL sanitizer stores an empty image as '#': that means "no image".
                'promo_image' => self::str($item['promo_image'] ?? '') === '#' ? '' : self::str($item['promo_image'] ?? ''),
                'promo_title' => self::str($item['promo_title'] ?? ''),
                'promo_url'   => self::str($item['promo_url'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Turn a nested category tree (Category::getNestedCategories shape: name, id_category,
     * link_rewrite, children) into menu items: top categories → items, their children → columns,
     * grandchildren → links.
     *
     * @param array<int|string, mixed> $nested Children of the menu root.
     * @param callable(int, string): string $linkFor id_category, link_rewrite → URL
     * @return array<int, array<string, mixed>>
     */
    public static function categoriesToItems(array $nested, callable $linkFor, int $maxItems = 8): array
    {
        $items = [];
        foreach (array_slice(array_values($nested), 0, max(1, min(self::MAX_ITEMS, $maxItems))) as $cat) {
            if (!is_array($cat) || empty($cat['name'])) {
                continue;
            }
            $columns = [];
            foreach (array_slice(array_values(is_array($cat['children'] ?? null) ? $cat['children'] : []), 0, self::MAX_COLUMNS) as $child) {
                if (!is_array($child) || empty($child['name'])) {
                    continue;
                }
                $links = [];
                foreach (array_slice(array_values(is_array($child['children'] ?? null) ? $child['children'] : []), 0, self::MAX_LINKS) as $grand) {
                    if (is_array($grand) && !empty($grand['name'])) {
                        $links[] = ['title' => (string) $grand['name'], 'url' => $linkFor((int) $grand['id_category'], (string) ($grand['link_rewrite'] ?? ''))];
                    }
                }
                $columns[] = [
                    'heading' => (string) $child['name'],
                    'url'     => $linkFor((int) $child['id_category'], (string) ($child['link_rewrite'] ?? '')),
                    'links'   => $links,
                ];
            }
            $items[] = [
                'title'       => (string) $cat['name'],
                'url'         => $linkFor((int) $cat['id_category'], (string) ($cat['link_rewrite'] ?? '')),
                'badge'       => '',
                'columns'     => $columns,
                'promo_image' => '',
                'promo_title' => '',
                'promo_url'   => '',
            ];
        }
        return $items;
    }

    /**
     * Load the shop's category tree as menu items (empty outside PrestaShop or on any error).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function loadCategoryItems(int $idLang, int $idShop, int $maxItems = 8): array
    {
        if (!class_exists('Category') || !class_exists('Context')) {
            return [];
        }
        try {
            $ctx = \Context::getContext();
            $rootId = (int) ($ctx->shop->id_category ?? 0);
            if ($rootId <= 0 && class_exists('Configuration')) {
                $rootId = (int) \Configuration::get('PS_HOME_CATEGORY');
            }
            $groups = method_exists('Customer', 'getGroupsStatic') && !empty($ctx->customer->id)
                ? \Customer::getGroupsStatic((int) $ctx->customer->id)
                : [(int) \Configuration::get('PS_UNIDENTIFIED_GROUP')];

            $tree = \Category::getNestedCategories($rootId, $idLang, true, $groups);
            $root = is_array($tree) ? reset($tree) : null;
            $children = is_array($root['children'] ?? null) ? $root['children'] : [];
            $link = $ctx->link;

            return self::categoriesToItems(
                $children,
                static fn(int $id, string $rewrite): string => (string) $link->getCategoryLink($id, $rewrite !== '' ? $rewrite : null, $idLang, null, $idShop),
                $maxItems
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Render the menu markup. Returns '' when there are no items.
     *
     * @param array<int, array<string, mixed>> $items normalised items
     */
    public static function render(array $items, string $navId, string $label, int $collapseBelowPx): string
    {
        if ($items === []) {
            return '';
        }
        $e = static fn(string $v): string => HtmlSanitizer::escapeOutput($v);
        $url = static fn(string $v): string => HtmlSanitizer::escapeOutput(HtmlSanitizer::sanitizeUrl($v !== '' ? $v : '#'));
        $id = (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $navId);
        $listId = $id . '-list';

        $html = '<nav class="mb-mm" id="' . $id . '" aria-label="' . $e($label) . '" data-mb-component="megamenu" data-mb-collapse="' . max(320, $collapseBelowPx) . '">'
            . '<button type="button" class="mb-mm-burger mb-interactive-hit" aria-expanded="false" aria-controls="' . $listId . '">'
            . '<span class="mb-mm-burger-icon" aria-hidden="true"></span><span>' . $e($label) . '</span></button>'
            . '<div class="mb-mm-drawer" id="' . $listId . '">'
            . '<div class="mb-mm-drawer-head"><span class="mb-mm-drawer-title">' . $e($label) . '</span>'
            . '<button type="button" class="mb-mm-close mb-interactive-hit" aria-label="Close menu">&times;</button></div>'
            . '<ul class="mb-mm-bar">';

        foreach ($items as $n => $item) {
            $hasPanel = $item['columns'] !== [] || ($item['promo_title'] ?? '') !== '' || ($item['promo_image'] ?? '') !== '';
            $panelId = $id . '-p' . $n;
            $html .= '<li class="mb-mm-item' . ($hasPanel ? ' has-panel' : '') . '">'
                . '<a class="mb-mm-link mb-interactive-hit" href="' . $url((string) $item['url']) . '">' . $e((string) $item['title'])
                . (($item['badge'] ?? '') !== '' ? ' <span class="mb-mm-badge">' . $e((string) $item['badge']) . '</span>' : '')
                . '</a>';

            if ($hasPanel) {
                $html .= '<button type="button" class="mb-mm-toggle mb-interactive-hit" aria-expanded="false" aria-controls="' . $panelId . '">'
                    . '<span class="mb-sr-only">' . $e((string) $item['title']) . ' submenu</span></button>'
                    . '<div class="mb-mm-panel" id="' . $panelId . '"><div class="mb-mm-panel-inner">';

                foreach ($item['columns'] as $col) {
                    $html .= '<div class="mb-mm-col">';
                    if ($col['heading'] !== '') {
                        $html .= '<p class="mb-mm-col-title">'
                            . ($col['url'] !== '' ? '<a href="' . $url($col['url']) . '">' . $e($col['heading']) . '</a>' : $e($col['heading']))
                            . '</p>';
                    }
                    if ($col['links'] !== []) {
                        $html .= '<ul class="mb-mm-links">';
                        foreach ($col['links'] as $link) {
                            $html .= '<li>' . ($link['url'] !== ''
                                ? '<a href="' . $url($link['url']) . '">' . $e($link['title']) . '</a>'
                                : '<span>' . $e($link['title']) . '</span>') . '</li>';
                        }
                        $html .= '</ul>';
                    }
                    $html .= '</div>';
                }

                if (($item['promo_title'] ?? '') !== '' || ($item['promo_image'] ?? '') !== '') {
                    $img = HtmlSanitizer::sanitizeUrl((string) $item['promo_image']);
                    $html .= '<a class="mb-mm-promo" href="' . $url((string) ($item['promo_url'] ?? '')) . '">'
                        . ($img !== '#' && $img !== '' ? '<img src="' . $e($img) . '" alt="" loading="lazy" />' : '')
                        . (($item['promo_title'] ?? '') !== '' ? '<span>' . $e((string) $item['promo_title']) . '</span>' : '')
                        . '</a>';
                }
                $html .= '</div></div>';
            }
            $html .= '</li>';
        }

        return $html . '</ul></div><div class="mb-mm-overlay" hidden></div></nav>';
    }

    private static function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
