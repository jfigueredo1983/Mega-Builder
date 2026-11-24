<?php
/**
 * Mega Builder — Stage 3 MySQL / MariaDB Schema Installer
 * Creates the 4 core tables with utf8mb4 support for PrestaShop 8.0 - 9.2+
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

$engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
$prefix = _DB_PREFIX_;

$queries = [
    "CREATE TABLE IF NOT EXISTS `{$prefix}mb_template` (
        `id_template` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `entity_kind` VARCHAR(32) NOT NULL DEFAULT 'page_template',
        `section_type` VARCHAR(64) NOT NULL DEFAULT 'homepage',
        `hook_name` VARCHAR(128) NOT NULL DEFAULT 'displayHome',
        `scope_rule` VARCHAR(32) NOT NULL DEFAULT 'all',
        `scope_targets` TEXT NULL,
        `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `priority` INT(11) NOT NULL DEFAULT 10,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_template`),
        KEY `idx_mb_kind_section` (`entity_kind`, `section_type`, `active`),
        KEY `idx_mb_hook_active` (`hook_name`, `active`, `priority`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `{$prefix}mb_template_lang` (
        `id_template` INT(11) UNSIGNED NOT NULL,
        `id_lang` INT(11) UNSIGNED NOT NULL,
        `title` VARCHAR(255) NOT NULL DEFAULT 'Untitled Template',
        `layout_json` LONGTEXT NOT NULL,
        `compiled_html` LONGTEXT NULL,
        `compiled_css` LONGTEXT NULL,
        PRIMARY KEY (`id_template`, `id_lang`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `{$prefix}mb_template_shop` (
        `id_template` INT(11) UNSIGNED NOT NULL,
        `id_shop` INT(11) UNSIGNED NOT NULL,
        `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id_template`, `id_shop`),
        KEY `idx_mb_shop_active` (`id_shop`, `active`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `{$prefix}mb_revision` (
        `id_revision` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_template` INT(11) UNSIGNED NOT NULL,
        `id_employee` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        `label` VARCHAR(191) NOT NULL DEFAULT 'Auto-save Checkpoint',
        `snapshot_json` LONGTEXT NOT NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_revision`),
        KEY `idx_mb_rev_tpl` (`id_template`, `date_add`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) {
        return false;
    }
}

return true;
