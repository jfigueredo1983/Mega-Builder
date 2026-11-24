<?php
/**
 * Mega Builder — Stage 5 SVG XML Upload & Icon Sanitizer (Shield 4)
 * Cleans uploaded or inline SVG documents by removing <script>, <foreignObject>,
 * inline on* attributes, and external javascript:/data: references.
 */

declare(strict_types=1);

namespace MegaBuilder\Security;

use DOMDocument;
use DOMElement;
use DOMNode;

final class SvgSanitizer
{
    private const FORBIDDEN_TAGS = [
        'script',
        'foreignobject',
        'iframe',
        'object',
        'embed',
        'applet',
        'audio',
        'video',
        'meta',
        'link',
        'base',
    ];

    /**
     * Sanitize raw SVG XML string. Returns cleaned SVG string, or null if malformed/unsafe.
     */
    public static function sanitizeSvg(string $svgContent): ?string
    {
        $svgContent = trim(str_replace("\0", '', $svgContent));
        if ($svgContent === '' || stripos($svgContent, '<svg') === false) {
            return null;
        }

        // Block DOCTYPE / ENTITY XXE declarations
        if (preg_match('/<!ENTITY|<!DOCTYPE\s+[^>]*\[/i', $svgContent)) {
            return null;
        }

        $prevUseErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadXML($svgContent, LIBXML_NONET | LIBXML_NOENT | LIBXML_NSCLEAN);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);

        if (!$loaded || !$dom->documentElement || strtolower($dom->documentElement->localName ?? $dom->documentElement->nodeName) !== 'svg') {
            return null;
        }

        self::cleanNode($dom->documentElement);

        $output = $dom->saveXML($dom->documentElement);
        return is_string($output) ? $output : null;
    }

    private static function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            // Remove unsafe attributes (on*, javascript: hrefs, style expressions)
            $attrsToRemove = [];
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $name = strtolower($attr->nodeName);
                    $val = trim($attr->nodeValue ?? '');
                    $normVal = strtolower((string) preg_replace('/[\s\x00-\x20]+/', '', $val));

                    if (str_starts_with($name, 'on')) {
                        $attrsToRemove[] = $attr->nodeName;
                    } elseif (in_array($name, ['href', 'xlink:href', 'src'], true)) {
                        if (
                            str_starts_with($normVal, 'javascript:') ||
                            str_starts_with($normVal, 'vbscript:') ||
                            str_starts_with($normVal, 'data:')
                        ) {
                            $attrsToRemove[] = $attr->nodeName;
                        }
                    } elseif ($name === 'style') {
                        $node->setAttribute($attr->nodeName, CssSanitizer::sanitizeCssBlock($val));
                    }
                }
            }
            foreach ($attrsToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }
        }

        // Traverse children backwards so removals are safe
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (!$child) {
                continue;
            }
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->localName ?? $child->nodeName);
                if (in_array($tag, self::FORBIDDEN_TAGS, true)) {
                    $node->removeChild($child);
                    continue;
                }
                if ($tag === 'style') {
                    $child->textContent = CssSanitizer::sanitizeCssBlock($child->textContent);
                    continue;
                }
            }
            self::cleanNode($child);
        }
    }
}
