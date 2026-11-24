<?php
/**
 * Mega Builder 1.1.0 upgrade: widgets without a storefront renderer are now "coming soon" and render
 * nothing, and Divider / Spacer render for real. Stored HTML is compiled at save time, so recompile
 * every saved template so published pages stop showing placeholder blocks.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

function upgrade_module_1_1_0($module): bool
{
    \MegaBuilder\Repository\TemplateRepository::recompileAll();
    return true;
}
