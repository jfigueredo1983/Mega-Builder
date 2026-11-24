<?php
/**
 * Mega Builder — Stage 4 Dual-Layer Server Payload Validator & Boundary Clamper
 * Enforces allowlists for all 15 Page Sections + 4 Component Sub-Studios,
 * clamps boundary numbers (-99999, 0, 9999999, NaN, 1e308), and sanitizes widget trees.
 */

declare(strict_types=1);

namespace MegaBuilder\Validator;

use MegaBuilder\Security\CssSanitizer;
use MegaBuilder\Security\HtmlSanitizer;

final class PayloadValidator
{
    /**
     * All 15 Page Template Sections locked in Stage 1 + 4 Component Sub-Studios.
     */
    public const PAGE_SECTIONS = [
        'header'        => ['label' => 'Header',               'default_hook' => 'displayNavFullWidth'],
        'homepage'      => ['label' => 'Homepage',             'default_hook' => 'displayHome'],
        'product'       => ['label' => 'Product Detail Page',  'default_hook' => 'displayFooterProduct'],
        'category'      => ['label' => 'Category Listing',     'default_hook' => 'displayCategoryHeader'],
        'footer'        => ['label' => 'Footer',               'default_hook' => 'displayFooter'],
        'cms'           => ['label' => 'CMS Content Page',     'default_hook' => 'displayCMSDisputeInformation'],
        'account'       => ['label' => 'Customer Account',     'default_hook' => 'displayCustomerAccount'],
        'prices_drop'   => ['label' => 'Prices Drop / Deals',  'default_hook' => 'displayWrapperTop'],
        'new_products'  => ['label' => 'New Products',         'default_hook' => 'displayWrapperTop'],
        'best_sales'    => ['label' => 'Best Sellers',         'default_hook' => 'displayWrapperTop'],
        'search'        => ['label' => 'Search Results',       'default_hook' => 'displayWrapperTop'],
        'checkout'      => ['label' => 'Checkout & Cart',      'default_hook' => 'displayShoppingCartFooter'],
        'manufacturers' => ['label' => 'Brands & Suppliers',   'default_hook' => 'displayWrapperTop'],
        'page_404'      => ['label' => '404 Not Found Page',   'default_hook' => 'displayWrapperTop'],
        'custom'        => ['label' => 'Custom Hook / Route',  'default_hook' => 'displayHome'],
    ];

    public const SUB_COMPONENTS = [
        'megamenu'  => ['label' => 'MegaMenu Builder',  'default_hook' => 'displayNavFullWidth'],
        'slider'    => ['label' => 'Sliders Builder',   'default_hook' => 'displayHome'],
        'card'      => ['label' => 'Cards Builder',     'default_hook' => 'displayHome'],
        'accordion' => ['label' => 'Accordion Builder', 'default_hook' => 'displayFooterProduct'],
    ];

    public const ALLOWED_HOOKS = [
        'displayHeader',
        'displayNavFullWidth',
        'displayTop',
        'displayHome',
        'displayFooterProduct',
        'displayProductAdditionalInfo',
        'displayLeftColumn',
        'displayRightColumn',
        'displayFooter',
        'displayCustomerAccount',
        'displayShoppingCartFooter',
        'displayWrapperTop',
        'displayWrapperBottom',
        'displayCategoryHeader',
        'displayCMSDisputeInformation',
    ];

    /**
     * Clamp any fuzzed or user numeric input (-99999, NaN, INF, 1e308) into [min, max].
     */
    public static function clampNumber(mixed $val, float $min, float $max, float $default): float
    {
        if (is_string($val)) {
            $val = trim($val);
            if ($val === '' || !is_numeric($val)) {
                return $default;
            }
        }
        if (!is_int($val) && !is_float($val) && !is_numeric($val)) {
            return $default;
        }
        $num = (float) $val;
        if (is_nan($num) || is_infinite($num)) {
            return $default;
        }
        if ($num < $min) {
            return $min;
        }
        if ($num > $max) {
            return $max;
        }
        return $num;
    }

    public static function clampInt(mixed $val, int $min, int $max, int $default): int
    {
        return (int) round(self::clampNumber($val, (float) $min, (float) $max, (float) $default));
    }

    /**
     * Validate and sanitize a full template payload before saving to MySQL.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, data: array<string, mixed>, errors: array<int, string>}
     */
    public static function validateTemplatePayload(array $payload): array
    {
        $errors = [];

        $entityKind = isset($payload['entity_kind']) && is_string($payload['entity_kind'])
            ? trim($payload['entity_kind'])
            : 'page_template';
        if (!in_array($entityKind, ['page_template', 'sub_component'], true)) {
            $entityKind = 'page_template';
        }

        $sectionType = isset($payload['section_type']) && is_string($payload['section_type'])
            ? strtolower(trim($payload['section_type']))
            : ($entityKind === 'sub_component' ? 'slider' : 'homepage');

        $allowedMap = $entityKind === 'sub_component' ? self::SUB_COMPONENTS : self::PAGE_SECTIONS;
        if (!isset($allowedMap[$sectionType])) {
            // Also check if sectionType exists in the other map and auto-align entity_kind
            if (isset(self::SUB_COMPONENTS[$sectionType])) {
                $entityKind = 'sub_component';
                $allowedMap = self::SUB_COMPONENTS;
            } elseif (isset(self::PAGE_SECTIONS[$sectionType])) {
                $entityKind = 'page_template';
                $allowedMap = self::PAGE_SECTIONS;
            } else {
                $errors[] = "Invalid section_type '{$sectionType}'.";
                $sectionType = $entityKind === 'sub_component' ? 'slider' : 'homepage';
            }
        }

        $title = HtmlSanitizer::sanitizePlainText($payload['title'] ?? 'Untitled Template', 180);
        if ($title === '') {
            $title = 'Untitled Template';
        }

        $defaultHook = $allowedMap[$sectionType]['default_hook'] ?? 'displayHome';
        $hookName = isset($payload['hook_name']) && is_string($payload['hook_name'])
            ? trim($payload['hook_name'])
            : $defaultHook;
        if (!in_array($hookName, self::ALLOWED_HOOKS, true)) {
            $hookName = $defaultHook;
        }

        $scopeRule = isset($payload['scope_rule']) && is_string($payload['scope_rule'])
            ? trim($payload['scope_rule'])
            : 'all';
        if (!in_array($scopeRule, ['all', 'specific_ids', 'conditions'], true)) {
            $scopeRule = 'all';
        }

        $scopeTargets = [];
        if (isset($payload['scope_targets']) && is_array($payload['scope_targets'])) {
            foreach (array_slice($payload['scope_targets'], 0, 500) as $id) {
                // Drop anything that is not a positive whole-number id. Clamping would turn
                // -1 into 1 and silently target an unrelated product.
                if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0 && (int) $id <= 99999999) {
                    $scopeTargets[] = (int) $id;
                }
            }
        }

        $active = self::clampInt($payload['active'] ?? 0, 0, 1, 0);
        $priority = self::clampInt($payload['priority'] ?? 10, 0, 1000, 10);

        $rawLayout = $payload['layout'] ?? ($payload['layout_json'] ?? []);
        if (is_string($rawLayout)) {
            $decoded = json_decode($rawLayout, true);
            $rawLayout = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rawLayout)) {
            $rawLayout = [];
        }

        $cleanLayout = self::sanitizeLayoutTree($rawLayout);

        return [
            'ok'     => count($errors) === 0,
            'errors' => $errors,
            'data'   => [
                'id_template'   => self::clampInt($payload['id_template'] ?? 0, 0, 99999999, 0),
                'entity_kind'   => $entityKind,
                'section_type'  => $sectionType,
                'hook_name'     => $hookName,
                'scope_rule'    => $scopeRule,
                'scope_targets' => array_values(array_unique($scopeTargets)),
                'active'        => $active,
                'priority'      => $priority,
                'title'         => $title,
                'revision_label'=> HtmlSanitizer::sanitizePlainText($payload['revision_label'] ?? 'Saved Checkpoint', 120),
                'layout'        => $cleanLayout,
            ],
        ];
    }

    /**
     * Sanitize and clamp the recursive Section/Container -> Column -> Widget JSON tree.
     *
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public static function sanitizeLayoutTree(array $layout): array
    {
        $sections = isset($layout['sections']) && is_array($layout['sections'])
            ? $layout['sections']
            : [];

        $cleanSections = [];
        foreach (array_slice($sections, 0, 60) as $idx => $sec) {
            if (!is_array($sec)) {
                continue;
            }
            $cleanSections[] = self::sanitizeSectionNode($sec, $idx);
        }

        return [
            'version'  => '1.0',
            'sections' => $cleanSections,
            'custom_css' => CssSanitizer::sanitizeCssBlock($layout['custom_css'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $sec
     * @return array<string, mixed>
     */
    private static function sanitizeSectionNode(array $sec, int $idx): array
    {
        $id = isset($sec['id']) && is_string($sec['id']) && preg_match('/^[a-zA-Z0-9_-]{2,48}$/', $sec['id'])
            ? $sec['id']
            : 'sec_' . ($idx + 1) . '_' . substr(md5((string) mt_rand()), 0, 6);

        $columns = isset($sec['columns']) && is_array($sec['columns']) ? $sec['columns'] : [];
        $cleanColumns = [];
        foreach (array_slice($columns, 0, 12) as $cIdx => $col) {
            if (!is_array($col)) {
                continue;
            }
            $cleanColumns[] = self::sanitizeColumnNode($col, $cIdx);
        }
        if (count($cleanColumns) === 0) {
            $cleanColumns[] = self::sanitizeColumnNode(['width' => 100, 'widgets' => []], 0);
        }

        return [
            'id'       => $id,
            'label'    => HtmlSanitizer::sanitizePlainText($sec['label'] ?? ('Section ' . ($idx + 1)), 80),
            'layout_mode' => in_array($sec['layout_mode'] ?? 'boxed', ['boxed', 'full_width', 'grid'], true)
                ? ($sec['layout_mode'] ?? 'boxed')
                : 'boxed',
            'max_width' => self::clampInt($sec['max_width'] ?? 1280, 320, 2560, 1280),
            'gap'       => self::sanitizeResponsiveInt($sec['gap'] ?? 24, 0, 120, 24),
            'padding_y' => self::sanitizeResponsiveInt($sec['padding_y'] ?? 40, 0, 240, 40),
            'padding_x' => self::sanitizeResponsiveInt($sec['padding_x'] ?? 20, 0, 240, 20),
            'bg_color'  => CssSanitizer::sanitizeColor($sec['bg_color'] ?? 'transparent', 'transparent'),
            'bg_image'  => HtmlSanitizer::sanitizeUrl($sec['bg_image'] ?? ''),
            'reverse_mobile' => !empty($sec['reverse_mobile']),
            'hide_desktop'   => !empty($sec['hide_desktop']),
            'hide_tablet'    => !empty($sec['hide_tablet']),
            'hide_mobile'    => !empty($sec['hide_mobile']),
            'columns'   => $cleanColumns,
        ];
    }

    /**
     * @param array<string, mixed> $col
     * @return array<string, mixed>
     */
    private static function sanitizeColumnNode(array $col, int $cIdx): array
    {
        $id = isset($col['id']) && is_string($col['id']) && preg_match('/^[a-zA-Z0-9_-]{2,48}$/', $col['id'])
            ? $col['id']
            : 'col_' . ($cIdx + 1) . '_' . substr(md5((string) mt_rand()), 0, 6);

        $widgets = isset($col['widgets']) && is_array($col['widgets']) ? $col['widgets'] : [];
        $cleanWidgets = [];
        foreach (array_slice($widgets, 0, 50) as $wIdx => $w) {
            if (!is_array($w)) {
                continue;
            }
            $cleanWidgets[] = self::sanitizeWidgetNode($w, $wIdx);
        }

        return [
            'id'           => $id,
            'width'        => self::clampNumber($col['width'] ?? 100, 10.0, 100.0, 100.0),
            'width_tablet' => self::clampNumber($col['width_tablet'] ?? ($col['width'] ?? 100), 10.0, 100.0, 100.0),
            'width_mobile' => self::clampNumber($col['width_mobile'] ?? 100, 10.0, 100.0, 100.0),
            'align_items'  => in_array($col['align_items'] ?? 'stretch', ['flex-start', 'center', 'flex-end', 'stretch'], true)
                ? ($col['align_items'] ?? 'stretch')
                : 'stretch',
            'padding'      => self::sanitizeResponsiveInt($col['padding'] ?? 12, 0, 160, 12),
            'bg_color'     => CssSanitizer::sanitizeColor($col['bg_color'] ?? 'transparent', 'transparent'),
            'border_radius'=> self::clampInt($col['border_radius'] ?? 0, 0, 120, 0),
            'widgets'      => $cleanWidgets,
        ];
    }

    /**
     * @param array<string, mixed> $w
     * @return array<string, mixed>
     */
    private static function sanitizeWidgetNode(array $w, int $wIdx): array
    {
        $id = isset($w['id']) && is_string($w['id']) && preg_match('/^[a-zA-Z0-9_-]{2,48}$/', $w['id'])
            ? $w['id']
            : 'w_' . ($wIdx + 1) . '_' . substr(md5((string) mt_rand()), 0, 6);

        $type = isset($w['type']) && is_string($w['type']) && preg_match('/^[a-z0-9_]{2,48}$/', $w['type'])
            ? $w['type']
            : 'heading';

        $content = isset($w['content']) && is_array($w['content']) ? self::sanitizeContentMap($w['content']) : [];
        $style = isset($w['style']) && is_array($w['style']) ? self::sanitizeStyleMap($w['style']) : [];
        $advanced = isset($w['advanced']) && is_array($w['advanced']) ? self::sanitizeAdvancedMap($w['advanced']) : [];

        return [
            'id'       => $id,
            'type'     => $type,
            'label'    => HtmlSanitizer::sanitizePlainText($w['label'] ?? ucfirst(str_replace('_', ' ', $type)), 80),
            'content'  => $content,
            'style'    => $style,
            'advanced' => $advanced,
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private static function sanitizeContentMap(array $content): array
    {
        $clean = [];
        foreach ($content as $key => $val) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_]{1,48}$/', $key)) {
                continue;
            }
            if (is_bool($val)) {
                $clean[$key] = $val;
            } elseif (is_int($val) || is_float($val)) {
                $clean[$key] = self::clampNumber($val, -999999.0, 999999.0, 0.0);
            } elseif (is_string($val)) {
                if (in_array($key, ['html', 'body', 'rich_text', 'description', 'answer', 'content'], true)) {
                    $clean[$key] = HtmlSanitizer::sanitizeRichHtml($val);
                } elseif (str_contains($key, 'url') || str_contains($key, 'link') || str_contains($key, 'src') || str_contains($key, 'image')) {
                    $clean[$key] = HtmlSanitizer::sanitizeUrl($val);
                } else {
                    $clean[$key] = HtmlSanitizer::sanitizePlainText($val, 1000);
                }
            } elseif (is_array($val)) {
                // Nested items array (e.g. slider slides, accordion items, megamenu columns, hotspot pins)
                $cleanList = [];
                foreach (array_slice($val, 0, 30) as $item) {
                    if (is_array($item)) {
                        $cleanList[] = self::sanitizeContentMap($item);
                    } elseif (is_scalar($item)) {
                        $cleanList[] = HtmlSanitizer::sanitizePlainText($item, 300);
                    }
                }
                $clean[$key] = $cleanList;
            }
        }
        return $clean;
    }

    /**
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    private static function sanitizeStyleMap(array $style): array
    {
        return [
            'text_color'     => CssSanitizer::sanitizeColor($style['text_color'] ?? '#0f172a', '#0f172a'),
            'bg_color'       => CssSanitizer::sanitizeColor($style['bg_color'] ?? 'transparent', 'transparent'),
            'accent_color'   => CssSanitizer::sanitizeColor($style['accent_color'] ?? '#2563eb', '#2563eb'),
            'border_color'   => CssSanitizer::sanitizeColor($style['border_color'] ?? '#e2e8f0', '#e2e8f0'),
            'font_size'      => self::sanitizeResponsiveInt($style['font_size'] ?? 16, 10, 120, 16),
            'font_weight'    => self::clampInt($style['font_weight'] ?? 500, 300, 900, 500),
            'line_height'    => self::clampNumber($style['line_height'] ?? 1.5, 0.9, 3.0, 1.5),
            'text_align'     => self::sanitizeResponsiveAlign($style['text_align'] ?? 'left'),
            'border_radius'  => self::clampInt($style['border_radius'] ?? 8, 0, 120, 8),
            'border_width'   => self::clampInt($style['border_width'] ?? 0, 0, 24, 0),
            'columns_count'  => self::sanitizeResponsiveInt($style['columns_count'] ?? 4, 1, 6, 4),
            'shadow'         => in_array($style['shadow'] ?? 'none', ['none', 'sm', 'md', 'lg', 'xl'], true)
                ? ($style['shadow'] ?? 'none')
                : 'none',
        ];
    }

    /**
     * @param array<string, mixed> $adv
     * @return array<string, mixed>
     */
    private static function sanitizeAdvancedMap(array $adv): array
    {
        return [
            'padding_y'    => self::sanitizeResponsiveInt($adv['padding_y'] ?? 12, 0, 200, 12),
            'padding_x'    => self::sanitizeResponsiveInt($adv['padding_x'] ?? 16, 0, 200, 16),
            'margin_y'     => self::sanitizeResponsiveInt($adv['margin_y'] ?? 0, -120, 200, 0),
            'z_index'      => self::clampInt($adv['z_index'] ?? 1, 0, 999, 1),
            'hide_desktop' => !empty($adv['hide_desktop']),
            'hide_tablet'  => !empty($adv['hide_tablet']),
            'hide_mobile'  => !empty($adv['hide_mobile']),
            'custom_class' => (string) preg_replace('/[^a-zA-Z0-9_ -]/', '', (string) ($adv['custom_class'] ?? '')),
            'custom_css'   => CssSanitizer::sanitizeCssBlock($adv['custom_css'] ?? ''),
        ];
    }

    /**
     * Normalize a responsive integer property `{ desktop, tablet, mobile }` with strict clamping.
     *
     * @return array{desktop: int, tablet: int, mobile: int}
     */
    public static function sanitizeResponsiveInt(mixed $val, int $min, int $max, int $default): array
    {
        if (is_array($val)) {
            $d = self::clampInt($val['desktop'] ?? $default, $min, $max, $default);
            $t = self::clampInt($val['tablet'] ?? $d, $min, $max, $d);
            $m = self::clampInt($val['mobile'] ?? $t, $min, $max, $t);
            return ['desktop' => $d, 'tablet' => $t, 'mobile' => $m];
        }
        $d = self::clampInt($val, $min, $max, $default);
        return ['desktop' => $d, 'tablet' => $d, 'mobile' => $d];
    }

    /**
     * @return array{desktop: string, tablet: string, mobile: string}
     */
    private static function sanitizeResponsiveAlign(mixed $val): array
    {
        $allowed = ['left', 'center', 'right', 'justify'];
        if (is_array($val)) {
            $d = in_array($val['desktop'] ?? 'left', $allowed, true) ? (string) ($val['desktop'] ?? 'left') : 'left';
            $t = in_array($val['tablet'] ?? $d, $allowed, true) ? (string) ($val['tablet'] ?? $d) : $d;
            $m = in_array($val['mobile'] ?? $t, $allowed, true) ? (string) ($val['mobile'] ?? $t) : $t;
            return ['desktop' => $d, 'tablet' => $t, 'mobile' => $m];
        }
        $d = in_array($val, $allowed, true) ? (string) $val : 'left';
        return ['desktop' => $d, 'tablet' => $d, 'mobile' => $d];
    }

    /**
     * Validate and clamp Global Template Settings (Colors, Typography, Layout, Breakpoints, 15 Page Rules, Performance).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validateGlobalSettings(array $input): array
    {
        $pageRules = [];
        $inputRules = isset($input['page_rules']) && is_array($input['page_rules']) ? $input['page_rules'] : [];
        foreach (self::PAGE_SECTIONS as $key => $meta) {
            $rule = isset($inputRules[$key]) && is_array($inputRules[$key]) ? $inputRules[$key] : [];
            $repMode = isset($rule['replace_mode']) && is_string($rule['replace_mode']) ? $rule['replace_mode'] : 'augment';
            $hkName = isset($rule['hook_name']) && is_string($rule['hook_name']) ? $rule['hook_name'] : $meta['default_hook'];
            $pageRules[$key] = [
                'enabled'      => isset($rule['enabled']) ? (bool) $rule['enabled'] : true,
                'replace_mode' => in_array($repMode, ['augment', 'replace'], true) ? $repMode : 'augment',
                'hook_name'    => in_array($hkName, self::ALLOWED_HOOKS, true) ? $hkName : $meta['default_hook'],
            ];
        }

        $colorsIn = isset($input['colors']) && is_array($input['colors']) ? $input['colors'] : [];
        $typoIn = isset($input['typography']) && is_array($input['typography']) ? $input['typography'] : [];
        $layoutIn = isset($input['layout']) && is_array($input['layout']) ? $input['layout'] : [];
        $compIn = isset($input['components']) && is_array($input['components']) ? $input['components'] : [];
        $lightIn = isset($input['lightbox']) && is_array($input['lightbox']) ? $input['lightbox'] : [];
        $perfIn = isset($input['performance']) && is_array($input['performance']) ? $input['performance'] : [];

        $btnStyle = isset($compIn['button_style']) && is_string($compIn['button_style']) ? $compIn['button_style'] : 'rounded';
        $cardElev = isset($compIn['card_elevation']) && is_string($compIn['card_elevation']) ? $compIn['card_elevation'] : 'sm';

        return [
            'colors' => [
                'primary'    => CssSanitizer::sanitizeColor($colorsIn['primary'] ?? '#2563eb', '#2563eb'),
                'secondary'  => CssSanitizer::sanitizeColor($colorsIn['secondary'] ?? '#0f172a', '#0f172a'),
                'accent'     => CssSanitizer::sanitizeColor($colorsIn['accent'] ?? '#d97706', '#d97706'),
                'surface'    => CssSanitizer::sanitizeColor($colorsIn['surface'] ?? '#ffffff', '#ffffff'),
                'surface_alt'=> CssSanitizer::sanitizeColor($colorsIn['surface_alt'] ?? '#f8fafc', '#f8fafc'),
                'text'       => CssSanitizer::sanitizeColor($colorsIn['text'] ?? '#0f172a', '#0f172a'),
                'muted'      => CssSanitizer::sanitizeColor($colorsIn['muted'] ?? '#475569', '#475569'),
                'border'     => CssSanitizer::sanitizeColor($colorsIn['border'] ?? '#e2e8f0', '#e2e8f0'),
            ],
            'typography' => [
                'heading_font'   => HtmlSanitizer::sanitizePlainText($typoIn['heading_font'] ?? 'Inter, system-ui, sans-serif', 80),
                'body_font'      => HtmlSanitizer::sanitizePlainText($typoIn['body_font'] ?? 'Inter, system-ui, sans-serif', 80),
                'base_size_px'   => self::clampInt($typoIn['base_size_px'] ?? 16, 12, 24, 16),
                'scale_ratio'    => self::clampNumber($typoIn['scale_ratio'] ?? 1.25, 1.05, 1.6, 1.25),
                'fluid_clamp'    => isset($typoIn['fluid_clamp']) ? (bool) $typoIn['fluid_clamp'] : true,
            ],
            'layout' => [
                'container_width_px'   => self::clampInt($layoutIn['container_width_px'] ?? 1280, 960, 1920, 1280),
                'section_gap_px'       => self::clampInt($layoutIn['section_gap_px'] ?? 24, 0, 96, 24),
                'card_radius_px'       => self::clampInt($layoutIn['card_radius_px'] ?? 12, 0, 48, 12),
                'tablet_breakpoint_px' => self::clampInt($layoutIn['tablet_breakpoint_px'] ?? 1024, 600, 1280, 1024),
                'mobile_breakpoint_px' => self::clampInt($layoutIn['mobile_breakpoint_px'] ?? 767, 320, 900, 767),
            ],
            'components' => [
                'button_style'   => in_array($btnStyle, ['sharp', 'rounded', 'pill'], true) ? $btnStyle : 'rounded',
                'card_elevation' => in_array($cardElev, ['none', 'sm', 'md', 'lg'], true) ? $cardElev : 'sm',
                'touch_min_px'   => self::clampInt($compIn['touch_min_px'] ?? 44, 44, 64, 44),
            ],
            'lightbox' => [
                'enabled'     => isset($lightIn['enabled']) ? (bool) $lightIn['enabled'] : true,
                'zoom_on_tap' => isset($lightIn['zoom_on_tap']) ? (bool) $lightIn['zoom_on_tap'] : true,
                'backdrop'    => CssSanitizer::sanitizeColor($lightIn['backdrop'] ?? 'rgba(15, 23, 42, 0.85)', 'rgba(15, 23, 42, 0.85)'),
            ],
            'performance' => [
                'precompile_css'       => isset($perfIn['precompile_css']) ? (bool) $perfIn['precompile_css'] : true,
                'lazy_load_images'     => isset($perfIn['lazy_load_images']) ? (bool) $perfIn['lazy_load_images'] : true,
                'front_admin_bar'      => isset($perfIn['front_admin_bar']) ? (bool) $perfIn['front_admin_bar'] : true,
                'admin_direct_buttons' => isset($perfIn['admin_direct_buttons']) ? (bool) $perfIn['admin_direct_buttons'] : true,
            ],
            'page_rules' => $pageRules,
            'design_md'  => HtmlSanitizer::sanitizePlainText($input['design_md'] ?? '# Mega Builder Design System Tokens', 8000),
        ];
    }

    /**
     * Validate Studio UI Theme configuration (Presets, Light/Dark, Density, Corner Radius, Brand Title/Logo).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validateStudioTheme(array $input): array
    {
        return [
            'preset'       => in_array($input['preset'] ?? 'obsidian_pro', ['obsidian_pro', 'nordic_light', 'emerald_craft', 'royal_velvet'], true)
                ? ($input['preset'] ?? 'obsidian_pro')
                : 'obsidian_pro',
            'mode'         => in_array($input['mode'] ?? 'light', ['light', 'dark'], true)
                ? ($input['mode'] ?? 'light')
                : 'light',
            'density'      => in_array($input['density'] ?? 'comfortable', ['compact', 'comfortable', 'spacious'], true)
                ? ($input['density'] ?? 'comfortable')
                : 'comfortable',
            'radius_px'    => self::clampInt($input['radius_px'] ?? 10, 0, 24, 10),
            'brand_title'  => HtmlSanitizer::sanitizePlainText($input['brand_title'] ?? 'Mega Builder Studio', 60) ?: 'Mega Builder Studio',
            'brand_logo'   => HtmlSanitizer::sanitizeUrl($input['brand_logo'] ?? ''),
            'accent_color' => CssSanitizer::sanitizeColor($input['accent_color'] ?? '#2563eb', '#2563eb'),
        ];
    }
}
