<?php
/**
 * Mega Builder — Signed, expiring storefront preview tokens.
 *
 * Token format: "<expiresAtUnix>.<hex HMAC-SHA256>", bound to one template id.
 */

declare(strict_types=1);

namespace MegaBuilder\Security;

final class PreviewToken
{
    public const DEFAULT_TTL_SECONDS = 900; // 15 minutes

    /**
     * Create a token for a template. Returns '' when no server secret is available.
     */
    public static function create(int $idTemplate, ?string $secret = null, int $ttl = self::DEFAULT_TTL_SECONDS, ?int $now = null): string
    {
        $secret = $secret ?? self::serverSecret();
        if ($secret === '' || $idTemplate <= 0) {
            return '';
        }
        $expires = ($now ?? time()) + max(60, $ttl);
        return $expires . '.' . self::sign($idTemplate, $expires, $secret);
    }

    /**
     * Verify a token for a template id. Fails closed on any malformed input or missing secret.
     */
    public static function verify(int $idTemplate, string $token, ?string $secret = null, ?int $now = null): bool
    {
        $secret = $secret ?? self::serverSecret();
        if ($secret === '' || $idTemplate <= 0) {
            return false;
        }
        // \z (not $): "$" would also accept a trailing newline after a valid token.
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{64})\z/', $token, $m)) {
            return false;
        }
        $expires = (int) $m[1];
        if ($expires < ($now ?? time())) {
            return false;
        }
        return hash_equals(self::sign($idTemplate, $expires, $secret), $m[2]);
    }

    private static function sign(int $idTemplate, int $expires, string $secret): string
    {
        return hash_hmac('sha256', 'mb_preview|' . $idTemplate . '|' . $expires, $secret);
    }

    /**
     * PrestaShop's per-install secret. Never fall back to a hardcoded key.
     */
    public static function serverSecret(): string
    {
        return defined('_COOKIE_KEY_') && is_string(_COOKIE_KEY_) ? (string) _COOKIE_KEY_ : '';
    }
}
