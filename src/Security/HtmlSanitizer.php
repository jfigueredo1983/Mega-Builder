<?php
/**
 * Mega Builder — Stage 5 HTML & Rich-Text XSS Sanitizer (Shield 2)
 * Follows frontend-security-coder rules: strips executable tags, event handlers, and dangerous URIs
 * without double-escaping UTF-8 characters or dynamic tags ({{mb:...}} / [mb_...]).
 */

declare(strict_types=1);

namespace MegaBuilder\Security;

final class HtmlSanitizer
{
    /**
     * Sanitize plain text (headings, labels, badges, titles) by stripping all HTML tags
     * and normalizing control characters while keeping raw UTF-8 (escaped once at render time).
     */
    public static function sanitizePlainText(mixed $input, int $maxLength = 500): string
    {
        if (!is_scalar($input)) {
            return '';
        }
        $str = (string) $input;
        // Strip null bytes and executable script/style blocks first
        $str = str_replace("\0", '', $str);
        $str = (string) preg_replace('#<(script|style|iframe|object|embed|svg)[^>]*>.*?</\1>#is', '', $str);
        $str = strip_tags($str);
        $str = trim($str);

        if (mb_strlen($str, 'UTF-8') > $maxLength) {
            $str = mb_substr($str, 0, $maxLength, 'UTF-8');
        }
        return $str;
    }

    /**
     * Escape plain text safely for HTML output without double-escaping existing entities
     * or corrupting {{mb:...}} dynamic store tags.
     */
    public static function escapeOutput(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize rich HTML content (Text Editor, Custom HTML widget, Alert boxes, Footers).
     */
    public static function sanitizeRichHtml(mixed $input): string
    {
        if (!is_scalar($input)) {
            return '';
        }
        $html = str_replace("\0", '', (string) $input);

        // 1. Remove dangerous block elements and their contents completely
        $html = (string) preg_replace(
            '#<(script|style|object|embed|applet|meta|link|base|frame|frameset)[^>]*>.*?</\1>#is',
            '',
            $html
        );
        // Also remove self-closing or unclosed dangerous tags
        $html = (string) preg_replace(
            '#</?(script|style|object|embed|applet|meta|link|base|frame|frameset|foreignObject)\b[^>]*>#is',
            '',
            $html
        );

        // 2. Strip all inline event handlers (onload, onerror, onclick, onmouseover, onfocus, etc.)
        $html = (string) preg_replace(
            '#\s+on[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#is',
            '',
            $html
        );

        // 3. Neutralize javascript:, vbscript:, and data:text/html inside href/src/action/xlink:href/formaction
        $html = (string) preg_replace_callback(
            '#\b(href|src|action|formaction|xlink:href|poster|data)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#is',
            static function (array $m): string {
                $attr = $m[1];
                $rawVal = trim($m[2], "\"' ");
                $normalized = strtolower((string) preg_replace('/[\s\x00-\x20]+/', '', html_entity_decode($rawVal, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (
                    str_starts_with($normalized, 'javascript:') ||
                    str_starts_with($normalized, 'vbscript:') ||
                    str_starts_with($normalized, 'data:text/html') ||
                    str_starts_with($normalized, 'data:image/svg+xml')
                ) {
                    return $attr . '="#"';
                }
                return $m[0];
            },
            $html
        );

        // 4. Sanitize iframes: only allow https:// youtube/vimeo/google-maps embeds with sandbox
        $html = (string) preg_replace_callback(
            '#<iframe\b([^>]*)>(.*?)</iframe>#is',
            static function (array $m): string {
                $attrs = $m[1];
                if (preg_match('#\bsrc\s*=\s*["\'](https://(?:www\.)?(?:youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|maps\.google\.com|www\.google\.com/maps)/[^"\']+)["\']#i', $attrs, $srcMatch)) {
                    $safeSrc = htmlspecialchars($srcMatch[1], ENT_QUOTES, 'UTF-8');
                    return '<iframe src="' . $safeSrc . '" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen></iframe>';
                }
                return '';
            },
            $html
        );

        return trim($html);
    }

    /**
     * Sanitize a URL string for links, buttons, and media sources.
     */
    public static function sanitizeUrl(mixed $url): string
    {
        if (!is_scalar($url)) {
            return '#';
        }
        $str = trim(str_replace("\0", '', (string) $url));
        if ($str === '') {
            return '#';
        }

        $normalized = strtolower((string) preg_replace('/[\s\x00-\x20]+/', '', html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // Protocol-relative ("//host") and backslash tricks ("/\host") point off-site; reject them.
        if (str_starts_with($normalized, '//') || str_starts_with($normalized, '/\\') || str_starts_with($normalized, '\\')) {
            return '#';
        }

        // Dynamic store tags (e.g. {{mb:product_url}}), in-page anchors and same-site paths.
        if (str_starts_with($str, '{{mb:') || str_starts_with($str, '#') || str_starts_with($str, '/')) {
            return $str;
        }

        // Anything with a scheme must use an allowed one.
        if (preg_match('#^([a-z][a-z0-9+.-]*):#', $normalized, $m)) {
            return in_array($m[1], ['http', 'https', 'mailto', 'tel'], true) ? $str : '#';
        }

        // Relative path such as "page.html" or "?q=1".
        return $str;
    }
}
