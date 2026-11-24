<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Security\PreviewToken;
use PHPUnit\Framework\TestCase;

final class PreviewTokenTest extends TestCase
{
    private const SECRET = 'test-secret-value';
    private const NOW = 1_800_000_000;

    private function token(int $id = 5): string
    {
        return PreviewToken::create($id, self::SECRET, 900, self::NOW);
    }

    public function testValidTokenVerifies(): void
    {
        self::assertTrue(PreviewToken::verify(5, $this->token(), self::SECRET, self::NOW + 10));
    }

    public function testTokenIsBoundToOneTemplate(): void
    {
        self::assertFalse(PreviewToken::verify(6, $this->token(5), self::SECRET, self::NOW + 10));
    }

    public function testTokenExpires(): void
    {
        self::assertTrue(PreviewToken::verify(5, $this->token(), self::SECRET, self::NOW + 900));
        self::assertFalse(PreviewToken::verify(5, $this->token(), self::SECRET, self::NOW + 901));
    }

    public function testWrongSecretAndTamperingFail(): void
    {
        $tok = $this->token();
        self::assertFalse(PreviewToken::verify(5, $tok, 'other-secret', self::NOW + 10));

        $flipped = substr($tok, 0, -1) . (str_ends_with($tok, 'a') ? 'b' : 'a');
        self::assertFalse(PreviewToken::verify(5, $flipped, self::SECRET, self::NOW + 10));

        // Pushing the expiry forward invalidates the signature.
        [$exp, $sig] = explode('.', $tok);
        self::assertFalse(PreviewToken::verify(5, ((int) $exp + 3600) . '.' . $sig, self::SECRET, self::NOW + 10));
    }

    public function testMalformedTokensFail(): void
    {
        foreach (['', 'abc', '123.xyz', '1800000900.' . str_repeat('g', 64), "1800000900.\n"] as $bad) {
            self::assertFalse(PreviewToken::verify(5, $bad, self::SECRET, self::NOW), "Accepted: {$bad}");
        }
        // A valid token with trailing junk must not pass.
        self::assertFalse(PreviewToken::verify(5, $this->token() . "\n", self::SECRET, self::NOW + 10));
        self::assertFalse(PreviewToken::verify(5, ' ' . $this->token(), self::SECRET, self::NOW + 10));
    }

    public function testFailsClosedWithoutSecretOrTemplate(): void
    {
        self::assertSame('', PreviewToken::create(5, '', 900, self::NOW));
        self::assertSame('', PreviewToken::create(0, self::SECRET, 900, self::NOW));
        self::assertFalse(PreviewToken::verify(5, $this->token(), '', self::NOW + 10));
        self::assertFalse(PreviewToken::verify(0, $this->token(), self::SECRET, self::NOW + 10));
    }

    public function testDefaultSecretIsEmptyOutsidePrestaShop(): void
    {
        // No _COOKIE_KEY_ in unit tests: there must be no hardcoded fallback key.
        self::assertSame('', PreviewToken::serverSecret());
        self::assertSame('', PreviewToken::create(5));
    }
}
