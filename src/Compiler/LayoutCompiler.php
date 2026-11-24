<?php
/**
 * Mega Builder — Stage 3 & Stage 6 Write-Time Responsive HTML5 & 3-Viewport CSS Compiler
 * Compiles layout_json into semantic HTML5 + scoped 3-tier responsive CSS (1440px, 768px, 390px).
 */

declare(strict_types=1);

namespace MegaBuilder\Compiler;

use MegaBuilder\Security\CssSanitizer;
use MegaBuilder\Security\HtmlSanitizer;
use MegaBuilder\Validator\PayloadValidator;

final class LayoutCompiler
{
    /**
     * Widget types with a real storefront renderer below. Any other registered type is
     * "coming soon": the studio shows it as unavailable and the storefront renders nothing
     * for it, instead of a placeholder block. Keep in sync with renderWidgetInnerHtml().
     */
    public const RENDERED_TYPES = [
        'heading', 'animated_headline', 'page_title', 'product_title', 'shop_title',
        'shop_logo',
        'text_editor', 'custom_html', 'product_description',
        'image', 'product_images',
        'button', 'add_to_cart', 'lightbox_trigger',
        'creative_slider', 'image_carousel', 'testimonial_carousel',
        'accordion', 'toggle', 'tabs',
        'nav_megamenu',
        'product_grid', 'product_carousel',
        'category_grid', 'articles_grid', 'trust_badges', 'account_nav_links',
        'product_box', 'image_box', 'icon_box', 'call_to_action', 'flip_box',
        'image_hotspot',
        'ajax_search',
        'email_subscription', 'contact_form', 'customer_login', 'customer_register', 'password_recovery',
        'product_price',
        'countdown',
        'ps_module_widget',
        'template_widget',
        'divider',
        'spacer',
    ];

    /** Tablet breakpoint of the layout being compiled; menus collapse into a drawer below it. */
    private static int $collapseBelowPx = 1024;

    public static function isRendered(string $type): bool
    {
        return in_array($type, self::RENDERED_TYPES, true);
    }

    /**
     * Compile a sanitized layout tree into ready-to-serve HTML and responsive CSS.
     *
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $globalSettings
     * @return array{html: string, css: string}
     */
    public static function compile(array $layout, array $globalSettings = []): array
    {
        $cleanLayout = PayloadValidator::sanitizeLayoutTree($layout);
        $global = PayloadValidator::validateGlobalSettings($globalSettings);

        $tabletBp = (int) ($global['layout']['tablet_breakpoint_px'] ?? 1024);
        self::$collapseBelowPx = $tabletBp;
        $mobileBp = (int) ($global['layout']['mobile_breakpoint_px'] ?? 767);
        $touchMin = (int) ($global['components']['touch_min_px'] ?? 44);

        $htmlParts = [];
        $desktopCss = [self::buildGlobalRootVariablesCss($global, $touchMin)];
        $tabletCss = [];
        $mobileCss = [];

        $htmlParts[] = '<div class="mb-storefront-root" data-mb-version="1.0">';

        foreach ($cleanLayout['sections'] as $sec) {
            $secId = HtmlSanitizer::escapeOutput((string) $sec['id']);
            $secClass = 'mb-section mb-sec-' . $secId;
            if (!empty($sec['hide_desktop'])) {
                $secClass .= ' mb-hide-desktop';
            }
            if (!empty($sec['hide_tablet'])) {
                $secClass .= ' mb-hide-tablet';
            }
            if (!empty($sec['hide_mobile'])) {
                $secClass .= ' mb-hide-mobile';
            }
            if (!empty($sec['reverse_mobile'])) {
                $secClass .= ' mb-reverse-mobile';
            }

            // Section responsive CSS
            $pyD = (int) ($sec['padding_y']['desktop'] ?? 36);
            $pyT = (int) ($sec['padding_y']['tablet'] ?? $pyD);
            $pyM = (int) ($sec['padding_y']['mobile'] ?? $pyT);

            $pxD = (int) ($sec['padding_x']['desktop'] ?? 20);
            $pxT = (int) ($sec['padding_x']['tablet'] ?? 16);
            $pxM = (int) ($sec['padding_x']['mobile'] ?? 14);

            $gapD = (int) ($sec['gap']['desktop'] ?? 24);
            $gapT = (int) ($sec['gap']['tablet'] ?? 18);
            $gapM = (int) ($sec['gap']['mobile'] ?? 14);

            $bgColor = CssSanitizer::sanitizeColor($sec['bg_color'] ?? 'transparent', 'transparent');
            $maxWidth = ($sec['layout_mode'] ?? 'boxed') === 'full_width' ? '100%' : ((int) ($sec['max_width'] ?? 1280)) . 'px';

            $desktopCss[] = ".mb-sec-{$secId} { background-color: {$bgColor}; padding: {$pyD}px {$pxD}px; box-sizing: border-box; width: 100%; max-width: 100%; overflow-x: hidden; overflow-x: clip; }";
            $desktopCss[] = ".mb-sec-{$secId} > .mb-section-inner { max-width: {$maxWidth}; margin: 0 auto; display: flex; flex-wrap: wrap; gap: {$gapD}px; width: 100%; box-sizing: border-box; }";

            $tabletCss[] = ".mb-sec-{$secId} { padding: {$pyT}px {$pxT}px; }";
            $tabletCss[] = ".mb-sec-{$secId} > .mb-section-inner { gap: {$gapT}px; }";

            $mobileCss[] = ".mb-sec-{$secId} { padding: {$pyM}px {$pxM}px; }";
            $mobileCss[] = ".mb-sec-{$secId} > .mb-section-inner { gap: {$gapM}px; flex-direction: " . (!empty($sec['reverse_mobile']) ? 'column-reverse' : 'column') . "; }";

            $htmlParts[] = '<section class="' . $secClass . '" data-mb-section-id="' . $secId . '">';
            $htmlParts[] = '<div class="mb-section-inner">';

            $colCount = max(1, count($sec['columns']));
            foreach ($sec['columns'] as $col) {
                $colId = HtmlSanitizer::escapeOutput((string) $col['id']);
                $wD = (float) ($col['width'] ?? (100 / $colCount));
                $wT = (float) ($col['width_tablet'] ?? $wD);
                $wM = (float) ($col['width_mobile'] ?? 100.0);

                $cPadD = (int) ($col['padding']['desktop'] ?? 12);
                $cPadT = (int) ($col['padding']['tablet'] ?? 10);
                $cPadM = (int) ($col['padding']['mobile'] ?? 8);

                $colBg = CssSanitizer::sanitizeColor($col['bg_color'] ?? 'transparent', 'transparent');
                $colRadius = (int) ($col['border_radius'] ?? 0);

                $flexCalcD = $colCount === 1 || $wD >= 99.0
                    ? '100%'
                    : "calc({$wD}% - " . round(($gapD * ($colCount - 1)) / $colCount, 2) . 'px)';
                $flexCalcT = $wT >= 99.0
                    ? '100%'
                    : "calc({$wT}% - " . round($gapT / 2, 2) . 'px)';

                $desktopCss[] = ".mb-col-{$colId} { flex: 1 1 {$flexCalcD}; max-width: {$flexCalcD}; min-width: 0; padding: {$cPadD}px; background-color: {$colBg}; border-radius: {$colRadius}px; box-sizing: border-box; display: flex; flex-direction: column; gap: 16px; }";
                $tabletCss[] = ".mb-col-{$colId} { flex: 1 1 {$flexCalcT}; max-width: {$flexCalcT}; padding: {$cPadT}px; }";
                $mobileCss[] = ".mb-col-{$colId} { flex: 1 1 100%; max-width: 100%; width: 100%; padding: {$cPadM}px; }";

                $htmlParts[] = '<div class="mb-column mb-col-' . $colId . '" data-mb-col-id="' . $colId . '">';

                foreach ($col['widgets'] as $widget) {
                    $wCompiled = self::compileWidget($widget);
                    $htmlParts[] = $wCompiled['html'];
                    $desktopCss[] = $wCompiled['css_desktop'];
                    if ($wCompiled['css_tablet'] !== '') {
                        $tabletCss[] = $wCompiled['css_tablet'];
                    }
                    if ($wCompiled['css_mobile'] !== '') {
                        $mobileCss[] = $wCompiled['css_mobile'];
                    }
                }

                $htmlParts[] = '</div>';
            }

            $htmlParts[] = '</div></section>';
        }

        $htmlParts[] = '</div>';

        if (!empty($cleanLayout['custom_css'])) {
            $desktopCss[] = $cleanLayout['custom_css'];
        }

        $fullCss = implode("\n", array_filter($desktopCss));
        if (count($tabletCss) > 0) {
            $fullCss .= "\n@media (max-width: {$tabletBp}px) {\n  " . implode("\n  ", array_filter($tabletCss)) . "\n}";
        }
        if (count($mobileCss) > 0) {
            $fullCss .= "\n@media (max-width: {$mobileBp}px) {\n  " . implode("\n  ", array_filter($mobileCss)) . "\n}";
        }

        return [
            'html' => implode("\n", $htmlParts),
            'css'  => $fullCss,
        ];
    }

    /**
     * @param array<string, mixed> $global
     */
    private static function buildGlobalRootVariablesCss(array $global, int $touchMin): string
    {
        $c = $global['colors'];
        $t = $global['typography'];
        $l = $global['layout'];

        return <<<CSS
.mb-storefront-root {
  --mb-primary: {$c['primary']};
  --mb-secondary: {$c['secondary']};
  --mb-accent: {$c['accent']};
  --mb-surface: {$c['surface']};
  --mb-surface-alt: {$c['surface_alt']};
  --mb-text: {$c['text']};
  --mb-muted: {$c['muted']};
  --mb-border: {$c['border']};
  --mb-font-heading: {$t['heading_font']};
  --mb-font-body: {$t['body_font']};
  --mb-base-size: {$t['base_size_px']}px;
  --mb-container-width: {$l['container_width_px']}px;
  --mb-card-radius: {$l['card_radius_px']}px;
  --mb-touch-min: {$touchMin}px;
  font-family: var(--mb-font-body);
  color: var(--mb-text);
  line-height: 1.5;
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
  overflow-x: clip; /* clip, unlike hidden, does not also clip vertically, so menu panels can drop down */
}
.mb-storefront-root *, .mb-storefront-root *::before, .mb-storefront-root *::after {
  box-sizing: border-box;
}
.mb-storefront-root img, .mb-storefront-root video, .mb-storefront-root iframe {
  max-width: 100%;
  height: auto;
}
.mb-storefront-root .mb-btn, .mb-storefront-root button, .mb-storefront-root input, .mb-storefront-root select, .mb-storefront-root .mb-interactive-hit {
  min-height: var(--mb-touch-min, 44px);
}
@media (min-width: 1025px) { .mb-hide-desktop { display: none !important; } }
@media (min-width: 768px) and (max-width: 1024px) { .mb-hide-tablet { display: none !important; } }
@media (max-width: 767px) { .mb-hide-mobile { display: none !important; } }
CSS;
    }

    /**
     * Compile any of the 77 widgets into semantic HTML + 3-tier responsive CSS.
     *
     * @param array<string, mixed> $w
     * @return array{html: string, css_desktop: string, css_tablet: string, css_mobile: string}
     */
    private static function compileWidget(array $w): array
    {
        $wId = HtmlSanitizer::escapeOutput((string) $w['id']);
        $type = (string) ($w['type'] ?? 'heading');

        // Coming-soon widgets have no storefront renderer yet: output nothing rather than a dummy block.
        if (!self::isRendered($type)) {
            return ['html' => '', 'css_desktop' => '', 'css_tablet' => '', 'css_mobile' => ''];
        }
        $c = is_array($w['content'] ?? null) ? $w['content'] : [];
        $s = is_array($w['style'] ?? null) ? $w['style'] : [];
        $adv = is_array($w['advanced'] ?? null) ? $w['advanced'] : [];

        $classes = ['mb-widget', 'mb-widget-' . $type, 'mb-w-' . $wId];
        if (!empty($adv['hide_desktop'])) {
            $classes[] = 'mb-hide-desktop';
        }
        if (!empty($adv['hide_tablet'])) {
            $classes[] = 'mb-hide-tablet';
        }
        if (!empty($adv['hide_mobile'])) {
            $classes[] = 'mb-hide-mobile';
        }
        if (!empty($adv['custom_class'])) {
            $classes[] = HtmlSanitizer::escapeOutput((string) $adv['custom_class']);
        }

        // Responsive style tokens
        $fsD = (int) ($s['font_size']['desktop'] ?? 16);
        $fsT = (int) ($s['font_size']['tablet'] ?? $fsD);
        $fsM = (int) ($s['font_size']['mobile'] ?? $fsT);

        $alignD = (string) ($s['text_align']['desktop'] ?? 'left');
        $alignT = (string) ($s['text_align']['tablet'] ?? $alignD);
        $alignM = (string) ($s['text_align']['mobile'] ?? $alignT);

        $pyD = (int) ($adv['padding_y']['desktop'] ?? 12);
        $pyT = (int) ($adv['padding_y']['tablet'] ?? 10);
        $pyM = (int) ($adv['padding_y']['mobile'] ?? 8);

        $pxD = (int) ($adv['padding_x']['desktop'] ?? 14);
        $pxT = (int) ($adv['padding_x']['tablet'] ?? 12);
        $pxM = (int) ($adv['padding_x']['mobile'] ?? 10);

        $colsD = max(1, (int) ($s['columns_count']['desktop'] ?? 4));
        $colsT = max(1, (int) ($s['columns_count']['tablet'] ?? min(2, $colsD)));
        $colsM = max(1, (int) ($s['columns_count']['mobile'] ?? 1));

        $textColor = CssSanitizer::sanitizeColor($s['text_color'] ?? '#0f172a', '#0f172a');
        $bgColor = CssSanitizer::sanitizeColor($s['bg_color'] ?? 'transparent', 'transparent');
        $accentColor = CssSanitizer::sanitizeColor($s['accent_color'] ?? '#2563eb', '#2563eb');
        $borderColor = CssSanitizer::sanitizeColor($s['border_color'] ?? '#e2e8f0', '#e2e8f0');
        $radius = (int) ($s['border_radius'] ?? 10);
        $borderW = (int) ($s['border_width'] ?? 0);

        $cssD = ".mb-w-{$wId} { color: {$textColor}; background-color: {$bgColor}; border-radius: {$radius}px; padding: {$pyD}px {$pxD}px; text-align: {$alignD}; font-size: {$fsD}px; --mb-w-accent: {$accentColor}; --mb-w-cols: {$colsD}; " . ($borderW > 0 ? "border: {$borderW}px solid {$borderColor};" : '') . " max-width: 100%; }";
        if (!empty($adv['custom_css'])) {
            $cssD .= "\n" . CssSanitizer::sanitizeCssBlock((string) $adv['custom_css']);
        }
        $cssT = ".mb-w-{$wId} { padding: {$pyT}px {$pxT}px; text-align: {$alignT}; font-size: {$fsT}px; --mb-w-cols: {$colsT}; }";
        $cssM = ".mb-w-{$wId} { padding: {$pyM}px {$pxM}px; text-align: {$alignM}; font-size: {$fsM}px; --mb-w-cols: {$colsM}; }";

        $innerHtml = self::renderWidgetInnerHtml($type, $c, $w['label'] ?? $type, (string) $w['id']);

        $html = '<div class="' . implode(' ', $classes) . '" data-mb-widget-id="' . $wId . '" data-mb-widget-type="' . HtmlSanitizer::escapeOutput($type) . '">'
            . $innerHtml
            . '</div>';

        return [
            'html'        => $html,
            'css_desktop' => $cssD,
            'css_tablet'  => $cssT,
            'css_mobile'  => $cssM,
        ];
    }

    /**
     * Render rich, domain-authentic semantic HTML for each of the 77 widget types.
     *
     * @param array<string, mixed> $c
     */
    private static function renderWidgetInnerHtml(string $type, array $c, string $fallbackLabel, string $widgetId = ''): string
    {
        $e = static fn(mixed $v, string $def = ''): string => HtmlSanitizer::escapeOutput(is_scalar($v) && (string) $v !== '' ? (string) $v : $def);

        switch ($type) {
            case 'heading':
            case 'animated_headline':
            case 'page_title':
            case 'product_title':
            case 'shop_title':
                $sub = $e($c['subtitle'] ?? ($c['brand'] ?? ($c['before_text'] ?? '')));
                $main = $e($c['text'] ?? ($c['title'] ?? ($c['highlight_text'] ?? $fallbackLabel)));
                $after = $e($c['after_text'] ?? '');
                return ($sub !== '' ? '<span class="mb-eyebrow">' . $sub . '</span>' : '')
                    . '<h2 class="mb-heading-title">' . $main . ($after !== '' ? ' <span class="mb-accent-text">' . $after . '</span>' : '') . '</h2>';

            case 'shop_logo':
                $name = $e($c['text'] ?? '{{mb:shop_name}}');
                $tag = $e($c['tagline'] ?? 'ARCHITECTURAL FURNITURE');
                return '<a href="/" class="mb-brand-logo mb-interactive-hit"><strong>' . $name . '</strong><span>' . $tag . '</span></a>';

            case 'text_editor':
            case 'custom_html':
            case 'product_description':
                $rich = HtmlSanitizer::sanitizeRichHtml($c['html'] ?? ($c['text'] ?? '<p>' . $e($fallbackLabel) . '</p>'));
                return '<div class="mb-prose">' . $rich . '</div>';

            case 'image':
            case 'product_images':
                $src = HtmlSanitizer::sanitizeUrl($c['src'] ?? 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=80');
                $alt = $e($c['alt'] ?? $fallbackLabel);
                $cap = $e($c['caption'] ?? '');
                return '<figure class="mb-figure"><img src="' . HtmlSanitizer::escapeOutput($src) . '" alt="' . $alt . '" loading="lazy" />'
                    . ($cap !== '' ? '<figcaption class="mb-figcaption">' . $cap . '</figcaption>' : '') . '</figure>';

            case 'button':
            case 'add_to_cart':
            case 'lightbox_trigger':
                $btnText = $e($c['text'] ?? ($c['button_text'] ?? $fallbackLabel));
                $url = HtmlSanitizer::sanitizeUrl($c['url'] ?? '#');
                $sec = $e($c['secondary_text'] ?? '');
                return '<div class="mb-btn-group">'
                    . '<a href="' . HtmlSanitizer::escapeOutput($url) . '" class="mb-btn mb-btn-primary mb-interactive-hit">' . $btnText . '</a>'
                    . ($sec !== '' ? '<a href="#swatch" class="mb-btn mb-btn-outline mb-interactive-hit">' . $sec . '</a>' : '')
                    . '</div>';

            case 'creative_slider':
            case 'image_carousel':
            case 'testimonial_carousel':
                $slides = is_array($c['items'] ?? null) && count($c['items']) > 0 ? $c['items'] : [
                    ['badge' => 'FEATURED COLLECTION', 'title' => $fallbackLabel, 'subtitle' => 'Handcrafted solid hardwood and Italian textiles.', 'cta_text' => 'Explore Now', 'src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1200&q=80'],
                ];
                $cards = [];
                foreach ($slides as $idx => $sl) {
                    if (!is_array($sl)) {
                        continue;
                    }
                    $sTitle = $e($sl['title'] ?? ('Slide ' . ($idx + 1)));
                    $sSub = $e($sl['subtitle'] ?? ($sl['answer'] ?? ''));
                    $sBadge = $e($sl['badge'] ?? 'SIGNATURE SERIES');
                    $sCta = $e($sl['cta_text'] ?? 'Shop Collection');
                    $sSrc = HtmlSanitizer::sanitizeUrl($sl['src'] ?? '');
                    $cards[] = '<div class="mb-slide-card' . ($idx === 0 ? ' is-active' : '') . '" data-mb-slide-index="' . $idx . '">'
                        . ($sSrc !== '#' && $sSrc !== '' ? '<div class="mb-slide-media"><img src="' . HtmlSanitizer::escapeOutput($sSrc) . '" alt="' . $sTitle . '" loading="lazy" /></div>' : '')
                        . '<div class="mb-slide-overlay">'
                        . '<span class="mb-badge">' . $sBadge . '</span>'
                        . '<h3 class="mb-slide-title">' . $sTitle . '</h3>'
                        . ($sSub !== '' ? '<p class="mb-slide-sub">' . $sSub . '</p>' : '')
                        . '<a href="#explore" class="mb-btn mb-btn-primary mb-interactive-hit">' . $sCta . '</a>'
                        . '</div></div>';
                }
                return '<div class="mb-slider-container" data-mb-component="slider">'
                    . '<div class="mb-slider-track">' . implode('', $cards) . '</div>'
                    . '<div class="mb-slider-controls">'
                    . '<button type="button" class="mb-slider-btn mb-prev mb-interactive-hit" aria-label="Previous slide">‹</button>'
                    . '<button type="button" class="mb-slider-btn mb-next mb-interactive-hit" aria-label="Next slide">›</button>'
                    . '</div></div>';

            case 'accordion':
            case 'toggle':
            case 'tabs':
                $items = is_array($c['items'] ?? null) && count($c['items']) > 0 ? $c['items'] : [
                    ['title' => $e($c['title'] ?? 'White-Glove Delivery & Materials'), 'answer' => $e($c['content'] ?? 'Included room-of-choice placement and 10-year structural frame warranty.')],
                ];
                $rows = [];
                foreach ($items as $idx => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $q = $e($item['title'] ?? ('Specification ' . ($idx + 1)));
                    $a = $e($item['answer'] ?? ($item['content'] ?? 'Crafted to rigorous architectural standards.'));
                    $openAttr = $idx === 0 ? ' open' : '';
                    $rows[] = '<details class="mb-accordion-item"' . $openAttr . '>'
                        . '<summary class="mb-accordion-summary mb-interactive-hit"><span>' . $q . '</span><span class="mb-acc-icon">+</span></summary>'
                        . '<div class="mb-accordion-body"><p>' . $a . '</p></div>'
                        . '</details>';
                }
                return '<div class="mb-accordion-group" data-mb-component="accordion">' . implode('', $rows) . '</div>';

            case 'nav_megamenu':
                $navId = 'mbmm-' . (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $widgetId);
                $label = is_scalar($c['menu_label'] ?? null) && trim((string) $c['menu_label']) !== '' ? trim((string) $c['menu_label']) : 'Menu';
                if (($c['source'] ?? 'manual') === 'categories') {
                    // Store categories change without a template save: resolved per request by DynamicTagResolver.
                    $max = PayloadValidator::clampInt($c['max_items'] ?? 8, 1, MegaMenuRenderer::MAX_ITEMS, 8);
                    return '[mb_category_menu id="' . $navId . '" max="' . $max . '" collapse="' . self::$collapseBelowPx . '" label="' . base64_encode($label) . '"]';
                }
                return MegaMenuRenderer::render(MegaMenuRenderer::normalizeItems($c['items'] ?? []), $navId, $label, self::$collapseBelowPx);

            case 'product_grid':
            case 'product_carousel':
                $title = $e($c['title'] ?? 'Curated Showroom Collection');
                $source = $e($c['source'] ?? 'featured');
                return '<div class="mb-catalog-section" data-mb-source="' . $source . '">'
                    . '<div class="mb-catalog-header"><h3 class="mb-section-heading">' . $title . '</h3></div>'
                    . '{{mb:product_cards_grid}}'
                    . '</div>';

            case 'category_grid':
            case 'articles_grid':
            case 'trust_badges':
            case 'account_nav_links':
                $title = $e($c['title'] ?? '');
                $items = is_array($c['items'] ?? null) && count($c['items']) > 0 ? $c['items'] : [
                    ['title' => 'Living Room Collection', 'answer' => '48 Pieces'],
                    ['title' => 'Dining & Kitchen',       'answer' => '36 Pieces'],
                    ['title' => 'Master Bedroom',         'answer' => '29 Pieces'],
                    ['title' => 'Executive Office',       'answer' => '22 Pieces'],
                ];
                $cards = [];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $t = $e($item['title'] ?? 'Collection');
                    $sub = $e($item['answer'] ?? '');
                    $src = HtmlSanitizer::sanitizeUrl($item['src'] ?? '');
                    $cards[] = '<div class="mb-grid-card">'
                        . ($src !== '#' && $src !== '' ? '<img src="' . HtmlSanitizer::escapeOutput($src) . '" alt="' . $t . '" class="mb-card-img" loading="lazy" />' : '')
                        . '<div class="mb-card-body"><strong>' . $t . '</strong>'
                        . ($sub !== '' ? '<span>' . $sub . '</span>' : '')
                        . '</div></div>';
                }
                return ($title !== '' ? '<h3 class="mb-section-heading">' . $title . '</h3>' : '')
                    . '<div class="mb-responsive-grid">' . implode('', $cards) . '</div>';

            case 'product_box':
            case 'image_box':
            case 'icon_box':
            case 'call_to_action':
            case 'flip_box':
                $badge = $e($c['badge'] ?? '');
                $title = $e($c['title'] ?? ($c['front_title'] ?? $fallbackLabel));
                $text = $e($c['text'] ?? ($c['front_desc'] ?? ($c['price'] ?? '')));
                $cta = $e($c['cta_text'] ?? 'Explore Product');
                $src = HtmlSanitizer::sanitizeUrl($c['src'] ?? '');
                return '<article class="mb-feature-card">'
                    . ($src !== '#' && $src !== '' ? '<div class="mb-feature-media"><img src="' . HtmlSanitizer::escapeOutput($src) . '" alt="' . $title . '" loading="lazy" /></div>' : '')
                    . '<div class="mb-feature-content">'
                    . ($badge !== '' ? '<span class="mb-badge">' . $badge . '</span>' : '')
                    . '<h3>' . $title . '</h3>'
                    . ($text !== '' ? '<p>' . $text . '</p>' : '')
                    . '<a href="#view" class="mb-btn mb-btn-primary mb-interactive-hit">' . $cta . '</a>'
                    . '</div></article>';

            case 'image_hotspot':
                $title = $e($c['title'] ?? 'Shop The Look');
                $src = HtmlSanitizer::sanitizeUrl($c['src'] ?? 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1000&q=80');
                $pins = is_array($c['items'] ?? null) ? $c['items'] : [];
                $pinHtml = [];
                foreach ($pins as $idx => $pin) {
                    if (!is_array($pin)) {
                        continue;
                    }
                    $x = PayloadValidator::clampInt($pin['x'] ?? 50, 5, 95, 50);
                    $y = PayloadValidator::clampInt($pin['y'] ?? 50, 5, 95, 50);
                    $pTitle = $e($pin['title'] ?? ('Look Item ' . ($idx + 1)));
                    $pinHtml[] = '<button type="button" class="mb-hotspot-pin mb-interactive-hit" style="left:' . $x . '%;top:' . $y . '%;" aria-label="' . $pTitle . '">'
                        . '<span class="mb-pin-dot">+</span><span class="mb-pin-tooltip">' . $pTitle . '</span></button>';
                }
                return '<div class="mb-hotspot-wrap"><h3>' . $title . '</h3>'
                    . '<div class="mb-hotspot-stage"><img src="' . HtmlSanitizer::escapeOutput($src) . '" alt="' . $title . '" loading="lazy" />'
                    . implode('', $pinHtml) . '</div></div>';

            case 'ajax_search':
                $ph = $e($c['placeholder'] ?? 'Search catalog...');
                return '<form action="/search" method="get" class="mb-search-form" role="search">'
                    . '<input type="search" name="s" class="mb-input mb-interactive-hit" placeholder="' . $ph . '" aria-label="' . $ph . '" />'
                    . '<button type="submit" class="mb-btn mb-btn-primary mb-interactive-hit">Search</button>'
                    . '</form>';

            case 'email_subscription':
            case 'contact_form':
            case 'customer_login':
            case 'customer_register':
            case 'password_recovery':
                $title = $e($c['title'] ?? $fallbackLabel);
                $sub = $e($c['subtitle'] ?? '');
                $btn = $e($c['button_text'] ?? 'Submit');
                return '<div class="mb-form-card">'
                    . '<h3>' . $title . '</h3>'
                    . ($sub !== '' ? '<p>' . $sub . '</p>' : '')
                    . '<div class="mb-form-row">'
                    . '<input type="email" class="mb-input mb-interactive-hit" placeholder="Email address" aria-label="Email address" />'
                    . '<button type="button" class="mb-btn mb-btn-primary mb-interactive-hit">' . $btn . '</button>'
                    . '</div></div>';

            case 'product_price':
                return '<div class="mb-price-block"><strong class="mb-price-current">' . $e($c['price'] ?? '{{mb:product_price}}') . '</strong>'
                    . '<span class="mb-badge">' . $e($c['badge'] ?? 'IN STOCK') . '</span></div>';

            case 'countdown':
                return '<div class="mb-countdown-box"><strong>' . $e($c['title'] ?? 'Limited Offer Ends In:') . '</strong>'
                    . '<div class="mb-countdown-digits">'
                    . '<span><b>04</b> Days</span><span><b>18</b> Hrs</span><span><b>42</b> Min</span>'
                    . '</div></div>';

            case 'ps_module_widget':
                $mod = $e($c['module_name'] ?? 'ps_featuredproducts');
                $hk = $e($c['hook_name'] ?? 'displayHome');
                return '<div class="mb-native-module-embed">[mb_module name="' . $mod . '" hook="' . $hk . '"]</div>';

            case 'divider':
                $style = in_array($c['style_type'] ?? 'solid', ['solid', 'dashed', 'dotted', 'double'], true) ? (string) $c['style_type'] : 'solid';
                $thickness = PayloadValidator::clampInt($c['thickness'] ?? 1, 1, 20, 1);
                return '<hr class="mb-divider" style="border:0;border-top:' . $thickness . 'px ' . $style . ' var(--mb-w-accent, currentColor);margin:0;" />';

            case 'spacer':
                $height = PayloadValidator::clampInt($c['height_px'] ?? 32, 0, 400, 32);
                return '<div class="mb-spacer" style="height:' . $height . 'px" aria-hidden="true"></div>';

            case 'template_widget':
                $tplId = PayloadValidator::clampInt($c['template_id'] ?? 0, 0, 999999, 0);
                return $tplId > 0
                    ? '<div class="mb-subcomponent-embed">[mb_template id="' . $tplId . '"]</div>'
                    : '<div class="mb-subcomponent-placeholder">Saved Sub-Studio Component Slot</div>';

            default:
                $title = $e($c['title'] ?? ($c['text'] ?? ($c['label'] ?? $fallbackLabel)));
                $sub = $e($c['subtitle'] ?? ($c['status'] ?? ($c['trail'] ?? ($c['chips'] ?? ($c['swatches'] ?? '')))));
                return '<div class="mb-generic-block"><strong>' . $title . '</strong>'
                    . ($sub !== '' ? '<p>' . $sub . '</p>' : '')
                    . '</div>';
        }
    }
}
