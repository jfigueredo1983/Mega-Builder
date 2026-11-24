<?php
/**
 * Mega Builder — Zero-Dependency PSR-4 Autoloader
 * Supports PrestaShop 8.0 through 9.2+ (PHP 8.1 - 8.5+)
 */

declare(strict_types=1);

namespace MegaBuilder;

final class Autoloader
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(static function (string $class): void {
            $prefix = 'MegaBuilder\\';
            $baseDir = __DIR__ . '/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        });

        self::$registered = true;
    }
}
