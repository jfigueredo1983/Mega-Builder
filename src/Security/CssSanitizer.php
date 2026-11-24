<?php
/**
 * Mega Builder — Stage 5 CSS Breakout & Style Sanitizer (Shield 3)
 * Prevents </style> tag breakouts, IE/legacy CSS expressions, and javascript: url() injections.
 */

declare(strict_types=1);

namespace MegaBuilder\Security;

final class CssSanitizer
{
    /**
     * Sanitize user-provided custom CSS or CSS property values.
     */
    public static function sanitizeCssBlock(mixed $css): string
    {
        if (!is_scalar($css)) {
            return '';
        }
        $clean = str_replace("\0", '', (string) $css);

        // 1. Strip any HTML closing or opening tags (e.g. </style><script>...)
        $clean = (string) preg_replace('#</?\s*[a-z!][^>]*>#is', '', $clean);
        $clean = str_ireplace(['</style', '<script', '<!--', '-->'], '', $clean);

        // 2. Strip legacy/dangerous CSS vectors: expression(), -moz-binding, behavior:, @import
        $clean = (string) preg_replace('#expression\s*\(#is', 'noop(', $clean);
        $clean = (string) preg_replace('#-moz-binding\s*:#is', 'noop:', $clean);
        $clean = (string) preg_replace('#behavior\s*:#is', 'noop:', $clean);
        $clean = (string) preg_replace('#@import\b[^;]*;?#is', '', $clean);

        // 3. Strip javascript: / vbscript: / data:text/html inside url(...)
        $clean = (string) preg_replace(
            '#url\s*\(\s*["\']?\s*(?:javascript|vbscript|data\s*:\s*text/html)[^)]*\)#is',
            'url()',
            $clean
        );

        return trim($clean);
    }

    /**
     * Sanitize a single CSS color token or hex/rgb/hsl/var() value.
     */
    public static function sanitizeColor(mixed $color, string $default = '#1e293b'): string
    {
        if (!is_scalar($color)) {
            return $default;
        }
        $val = trim((string) $color);
        if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9,\s.%\/]+\)|hsla?\([0-9,\s.deg%\/]+\)|var\(--[a-zA-Z0-9_-]+\)|transparent|inherit|currentColor)$/', $val)) {
            return $val;
        }
        return $default;
    }
}
