<?php
/**
 * Mega Builder 1.2.0 upgrade: the MegaMenu widget now renders an interactive menu (dropdowns,
 * keyboard, mobile drawer, live store categories). Stored HTML is compiled at save time, so recompile
 * every saved template so published menus get the new markup.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

function upgrade_module_1_2_0($module): bool
{
    \MegaBuilder\Repository\TemplateRepository::recompileAll();
    return true;
}
