<?php
/**
 * Mega Builder — Non-Destructive Uninstaller
 * By default, preserves merchant templates unless MEGABUILDER_DROP_ON_UNINSTALL is explicitly enabled.
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if ((int) Configuration::get('MEGABUILDER_DROP_ON_UNINSTALL') === 1) {
    $prefix = _DB_PREFIX_;
    $tables = [
        "{$prefix}mb_revision",
        "{$prefix}mb_template_shop",
        "{$prefix}mb_template_lang",
        "{$prefix}mb_template",
    ];

    foreach ($tables as $table) {
        Db::getInstance()->execute("DROP TABLE IF EXISTS `{$table}`");
    }
}

return true;
