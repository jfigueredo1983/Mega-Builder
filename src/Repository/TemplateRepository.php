<?php
/**
 * Mega Builder — Stage 3 Typed PSR-4 Database Repository
 * Manages PREFIX_mb_template, _lang, _shop, _revision, andps_configuration across PrestaShop 8.0 - 9.2+.
 */

declare(strict_types=1);

namespace MegaBuilder\Repository;

use MegaBuilder\Compiler\DynamicTagResolver;
use MegaBuilder\Compiler\LayoutCompiler;
use MegaBuilder\Compiler\WidgetRegistry;
use MegaBuilder\Security\HtmlSanitizer;
use MegaBuilder\Validator\PayloadValidator;

final class TemplateRepository
{
    private const MAX_REVISIONS_PER_TEMPLATE = 25;

    /**
     * Ensure default global settings and starter draft templates exist on first run.
     */
    public static function ensureDefaultsSeeded(int $idLang = 1, int $idShop = 1): void
    {
        if (!class_exists('Configuration') || !class_exists('Db')) {
            return;
        }

        if (!\Configuration::get('MEGABUILDER_GLOBAL_SETTINGS')) {
            $defaults = PayloadValidator::validateGlobalSettings([]);
            \Configuration::updateValue('MEGABUILDER_GLOBAL_SETTINGS', json_encode($defaults, JSON_UNESCAPED_UNICODE));
        }

        if (!\Configuration::get('MEGABUILDER_STUDIO_THEME')) {
            $themeDefaults = PayloadValidator::validateStudioTheme([]);
            \Configuration::updateValue('MEGABUILDER_STUDIO_THEME', json_encode($themeDefaults, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getGlobalSettings(): array
    {
        if (!class_exists('Configuration')) {
            return PayloadValidator::validateGlobalSettings([]);
        }
        $raw = (string) \Configuration::get('MEGABUILDER_GLOBAL_SETTINGS');
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        return PayloadValidator::validateGlobalSettings(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function saveGlobalSettings(array $input): array
    {
        $clean = PayloadValidator::validateGlobalSettings($input);
        if (class_exists('Configuration')) {
            \Configuration::updateValue('MEGABUILDER_GLOBAL_SETTINGS', json_encode($clean, JSON_UNESCAPED_UNICODE));
        }
        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getStudioTheme(): array
    {
        if (!class_exists('Configuration')) {
            return PayloadValidator::validateStudioTheme([]);
        }
        $raw = (string) \Configuration::get('MEGABUILDER_STUDIO_THEME');
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        return PayloadValidator::validateStudioTheme(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function saveStudioTheme(array $input): array
    {
        $clean = PayloadValidator::validateStudioTheme($input);
        if (class_exists('Configuration')) {
            \Configuration::updateValue('MEGABUILDER_STUDIO_THEME', json_encode($clean, JSON_UNESCAPED_UNICODE));
        }
        return $clean;
    }

    /**
     * List templates filtered by optional entity_kind and section_type.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listTemplates(?string $entityKind = null, ?string $sectionType = null, int $idLang = 1, int $idShop = 1): array
    {
        if (!class_exists('Db')) {
            return [];
        }
        $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $where = ['1 = 1'];
        if ($entityKind !== null && $entityKind !== '' && $entityKind !== 'all') {
            $where[] = "t.entity_kind = '" . pSQL($entityKind) . "'";
        }
        if ($sectionType !== null && $sectionType !== '' && $sectionType !== 'all') {
            $where[] = "t.section_type = '" . pSQL($sectionType) . "'";
        }

        $sql = "SELECT t.*, tl.title, tl.layout_json,
                       (SELECT COUNT(*) FROM `{$prefix}mb_revision` r WHERE r.id_template = t.id_template) AS revision_count
                FROM `{$prefix}mb_template` t
                LEFT JOIN `{$prefix}mb_template_lang` tl ON (t.id_template = tl.id_template AND tl.id_lang = " . (int) $idLang . ")
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.date_upd DESC, t.id_template DESC";

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $r) {
            $layout = json_decode((string) ($r['layout_json'] ?? '{}'), true);
            $sectionsCount = is_array($layout['sections'] ?? null) ? count($layout['sections']) : 0;
            $targets = json_decode((string) ($r['scope_targets'] ?? '[]'), true);

            $result[] = [
                'id_template'    => (int) $r['id_template'],
                'entity_kind'    => (string) $r['entity_kind'],
                'section_type'   => (string) $r['section_type'],
                'hook_name'      => (string) $r['hook_name'],
                'scope_rule'     => (string) $r['scope_rule'],
                'scope_targets'  => is_array($targets) ? $targets : [],
                'active'         => (int) $r['active'],
                'priority'       => (int) $r['priority'],
                'title'          => (string) ($r['title'] ?: ('Template #' . $r['id_template'])),
                'sections_count' => $sectionsCount,
                'revision_count' => (int) ($r['revision_count'] ?? 0),
                'date_add'       => (string) $r['date_add'],
                'date_upd'       => (string) $r['date_upd'],
            ];
        }
        return $result;
    }

    /**
     * Get a single template with full layout tree and revision history.
     *
     * @return array<string, mixed>|null
     */
    public static function getTemplate(int $idTemplate, int $idLang = 1): ?array
    {
        if ($idTemplate <= 0 || !class_exists('Db')) {
            return null;
        }
        $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $row = \Db::getInstance()->getRow(
            "SELECT t.*, tl.title, tl.layout_json, tl.compiled_html, tl.compiled_css
             FROM `{$prefix}mb_template` t
             LEFT JOIN `{$prefix}mb_template_lang` tl ON (t.id_template = tl.id_template AND tl.id_lang = " . (int) $idLang . ")
             WHERE t.id_template = " . (int) $idTemplate
        );
        if (!is_array($row) || empty($row['id_template'])) {
            return null;
        }

        $layout = json_decode((string) ($row['layout_json'] ?? '{}'), true);
        if (!is_array($layout) || empty($layout['sections'])) {
            $bp = WidgetRegistry::getStarterBlueprints()[(string) $row['section_type']] ?? null;
            $layout = is_array($bp['layout'] ?? null) ? $bp['layout'] : ['version' => '1.0', 'sections' => []];
        }

        $revisions = \Db::getInstance()->executeS(
            "SELECT id_revision, id_employee, label, date_add
             FROM `{$prefix}mb_revision`
             WHERE id_template = " . (int) $idTemplate . "
             ORDER BY id_revision DESC
             LIMIT " . self::MAX_REVISIONS_PER_TEMPLATE
        );

        $targets = json_decode((string) ($row['scope_targets'] ?? '[]'), true);

        return [
            'id_template'   => (int) $row['id_template'],
            'entity_kind'   => (string) $row['entity_kind'],
            'section_type'  => (string) $row['section_type'],
            'hook_name'     => (string) $row['hook_name'],
            'scope_rule'    => (string) $row['scope_rule'],
            'scope_targets' => is_array($targets) ? $targets : [],
            'active'        => (int) $row['active'],
            'priority'      => (int) $row['priority'],
            'title'         => (string) ($row['title'] ?: ('Template #' . $row['id_template'])),
            'layout'        => $layout,
            'compiled_html' => (string) ($row['compiled_html'] ?? ''),
            'compiled_css'  => (string) ($row['compiled_css'] ?? ''),
            'revisions'     => is_array($revisions) ? $revisions : [],
            'date_add'      => (string) $row['date_add'],
            'date_upd'      => (string) $row['date_upd'],
        ];
    }

    /**
     * Validate, compile, save a template across all active languages, and record a revision snapshot.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, data: array<string, mixed>, errors: array<int, string>}
     */
    public static function saveTemplate(array $payload, int $idLang = 1, int $idShop = 1, int $idEmployee = 1): array
    {
        $validated = PayloadValidator::validateTemplatePayload($payload);
        if (!$validated['ok']) {
            return $validated;
        }
        $d = $validated['data'];

        // If layout is empty and starter_blueprint is requested, seed from blueprint
        if (empty($d['layout']['sections']) && !empty($payload['use_starter_blueprint'])) {
            $bps = WidgetRegistry::getStarterBlueprints();
            if (isset($bps[$d['section_type']]['layout'])) {
                $d['layout'] = PayloadValidator::sanitizeLayoutTree($bps[$d['section_type']]['layout']);
            }
        }

        $globalSettings = self::getGlobalSettings();
        $compiled = LayoutCompiler::compile($d['layout'], $globalSettings);

        $layoutJson = (string) json_encode($d['layout'], JSON_UNESCAPED_UNICODE);
        $targetsJson = (string) json_encode($d['scope_targets'], JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');

        if (!class_exists('Db')) {
            return [
                'ok'     => true,
                'errors' => [],
                'data'   => array_merge($d, [
                    'id_template'   => $d['id_template'] ?: 1,
                    'compiled_html' => $compiled['html'],
                    'compiled_css'  => $compiled['css'],
                ]),
            ];
        }

        $db = \Db::getInstance();
        $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $idTemplate = (int) $d['id_template'];

        if ($idTemplate > 0) {
            $exists = (int) $db->getValue("SELECT id_template FROM `{$prefix}mb_template` WHERE id_template = {$idTemplate}");
            if ($exists <= 0) {
                $idTemplate = 0;
            }
        }

        if ($idTemplate > 0) {
            $db->update('mb_template', [
                'entity_kind'   => pSQL($d['entity_kind']),
                'section_type'  => pSQL($d['section_type']),
                'hook_name'     => pSQL($d['hook_name']),
                'scope_rule'    => pSQL($d['scope_rule']),
                'scope_targets' => pSQL($targetsJson),
                'active'        => (int) $d['active'],
                'priority'      => (int) $d['priority'],
                'date_upd'      => pSQL($now),
            ], 'id_template = ' . $idTemplate);
        } else {
            $db->insert('mb_template', [
                'entity_kind'   => pSQL($d['entity_kind']),
                'section_type'  => pSQL($d['section_type']),
                'hook_name'     => pSQL($d['hook_name']),
                'scope_rule'    => pSQL($d['scope_rule']),
                'scope_targets' => pSQL($targetsJson),
                'active'        => (int) $d['active'],
                'priority'      => (int) $d['priority'],
                'date_add'      => pSQL($now),
                'date_upd'      => pSQL($now),
            ]);
            $idTemplate = (int) $db->Insert_ID();
        }

        // Save for all installed PrestaShop languages so switching store language works seamlessly
        $langIds = [$idLang];
        if (class_exists('Language')) {
            foreach (\Language::getLanguages(false) as $l) {
                $langIds[] = (int) $l['id_lang'];
            }
        }
        $langIds = array_values(array_unique($langIds));

        foreach ($langIds as $lId) {
            $db->execute(
                "REPLACE INTO `{$prefix}mb_template_lang` (`id_template`, `id_lang`, `title`, `layout_json`, `compiled_html`, `compiled_css`)
                 VALUES (
                    " . (int) $idTemplate . ",
                    " . (int) $lId . ",
                    '" . pSQL($d['title']) . "',
                    '" . pSQL($layoutJson, true) . "',
                    '" . pSQL($compiled['html'], true) . "',
                    '" . pSQL($compiled['css'], true) . "'
                 )"
            );
        }

        // Save Multistore mapping
        $db->execute(
            "REPLACE INTO `{$prefix}mb_template_shop` (`id_template`, `id_shop`, `active`)
             VALUES (" . (int) $idTemplate . ", " . (int) $idShop . ", " . (int) $d['active'] . ")"
        );

        // Save Revision History snapshot and prune to MAX_REVISIONS_PER_TEMPLATE
        $snapshot = (string) json_encode([
            'title'   => $d['title'],
            'layout'  => $d['layout'],
        ], JSON_UNESCAPED_UNICODE);

        $db->insert('mb_revision', [
            'id_template'   => $idTemplate,
            'id_employee'   => $idEmployee,
            'label'         => pSQL($d['revision_label']),
            'snapshot_json' => pSQL($snapshot, true),
            'date_add'      => pSQL($now),
        ]);
        $revisionId = (int) $db->Insert_ID();

        $oldRevs = $db->executeS(
            "SELECT id_revision FROM `{$prefix}mb_revision`
             WHERE id_template = {$idTemplate}
             ORDER BY id_revision DESC
             LIMIT " . self::MAX_REVISIONS_PER_TEMPLATE . ", 100"
        );
        if (is_array($oldRevs) && count($oldRevs) > 0) {
            $idsToDelete = array_map(static fn(array $r): int => (int) $r['id_revision'], $oldRevs);
            $db->execute("DELETE FROM `{$prefix}mb_revision` WHERE id_revision IN (" . implode(',', $idsToDelete) . ")");
        }

        $saved = self::getTemplate($idTemplate, $idLang);

        return [
            'ok'     => true,
            'errors' => [],
            'data'   => array_merge($saved ?? $d, [
                'id_template' => $idTemplate,
                'revision_id' => $revisionId,
            ]),
        ];
    }

    /**
     * Toggle template active status (0 = Draft, 1 = Published).
     */
    public static function toggleStatus(int $idTemplate, int $active, int $idShop = 1): bool
    {
        if ($idTemplate <= 0 || !class_exists('Db')) {
            return false;
        }
        $state = $active ? 1 : 0;
        $db = \Db::getInstance();
        $db->update('mb_template', [
            'active'   => $state,
            'date_upd' => pSQL(date('Y-m-d H:i:s')),
        ], 'id_template = ' . $idTemplate);
        $db->update('mb_template_shop', [
            'active' => $state,
        ], 'id_template = ' . $idTemplate . ' AND id_shop = ' . $idShop);
        return true;
    }

    /**
     * Restore a template from a saved revision snapshot.
     *
     * @return array<string, mixed>|null
     */
    public static function restoreRevision(int $idTemplate, int $idRevision, int $idLang = 1, int $idShop = 1, int $idEmployee = 1): ?array
    {
        if ($idTemplate <= 0 || $idRevision <= 0 || !class_exists('Db')) {
            return null;
        }
        $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $rev = \Db::getInstance()->getRow(
            "SELECT * FROM `{$prefix}mb_revision`
             WHERE id_revision = " . (int) $idRevision . " AND id_template = " . (int) $idTemplate
        );
        if (!is_array($rev) || empty($rev['snapshot_json'])) {
            return null;
        }
        $snap = json_decode((string) $rev['snapshot_json'], true);
        if (!is_array($snap)) {
            return null;
        }
        $current = self::getTemplate($idTemplate, $idLang);
        if (!$current) {
            return null;
        }

        $res = self::saveTemplate([
            'id_template'    => $idTemplate,
            'entity_kind'    => $current['entity_kind'],
            'section_type'   => $current['section_type'],
            'hook_name'      => $current['hook_name'],
            'scope_rule'     => $current['scope_rule'],
            'scope_targets'  => $current['scope_targets'],
            'active'         => $current['active'],
            'priority'       => $current['priority'],
            'title'          => $snap['title'] ?? $current['title'],
            'layout'         => $snap['layout'] ?? $current['layout'],
            'revision_label' => 'Restored Revision #' . $idRevision,
        ], $idLang, $idShop, $idEmployee);

        return $res['ok'] ? $res['data'] : null;
    }

    /**
     * Duplicate an existing template as a Draft copy.
     *
     * @return array<string, mixed>|null
     */
    public static function duplicateTemplate(int $idTemplate, int $idLang = 1, int $idShop = 1, int $idEmployee = 1): ?array
    {
        $source = self::getTemplate($idTemplate, $idLang);
        if (!$source) {
            return null;
        }
        $res = self::saveTemplate([
            'id_template'    => 0,
            'entity_kind'    => $source['entity_kind'],
            'section_type'   => $source['section_type'],
            'hook_name'      => $source['hook_name'],
            'scope_rule'     => $source['scope_rule'],
            'scope_targets'  => $source['scope_targets'],
            'active'         => 0,
            'priority'       => $source['priority'],
            'title'          => $source['title'] . ' (Copy)',
            'layout'         => $source['layout'],
            'revision_label' => 'Duplicated from #' . $idTemplate,
        ], $idLang, $idShop, $idEmployee);

        return $res['ok'] ? $res['data'] : null;
    }

    /**
     * Delete a template and all its language, shop, and revision rows.
     */
    public static function deleteTemplate(int $idTemplate): bool
    {
        if ($idTemplate <= 0 || !class_exists('Db')) {
            return false;
        }
        $db = \Db::getInstance();
        $db->delete('mb_revision', 'id_template = ' . $idTemplate);
        $db->delete('mb_template_shop', 'id_template = ' . $idTemplate);
        $db->delete('mb_template_lang', 'id_template = ' . $idTemplate);
        $db->delete('mb_template', 'id_template = ' . $idTemplate);
        return true;
    }

    /**
     * Render a live preview or active storefront section with resolved Dynamic Tags and embedded Sub-Studios.
     *
     * @param array<string, mixed> $layout
     * @return array{html: string, css: string}
     */
    public static function renderLiveLayout(array $layout): array
    {
        $compiled = LayoutCompiler::compile($layout, self::getGlobalSettings());
        $resolvedHtml = DynamicTagResolver::resolve(
            $compiled['html'],
            [],
            static function (int $subId): ?array {
                $sub = self::getTemplate($subId);
                if (!$sub) {
                    return null;
                }
                return [
                    'html' => (string) $sub['compiled_html'],
                    'css'  => (string) $sub['compiled_css'],
                ];
            }
        );

        return [
            'html' => $resolvedHtml,
            'css'  => $compiled['css'],
        ];
    }

    /**
     * Fetch and resolve all active templates matching a given storefront section_type or hook_name.
     *
     * @return array{html: string, css: string}
     */
    public static function renderActiveForHookOrSection(string $hookName, ?string $sectionType = null, int $idLang = 1): array
    {
        if (!class_exists('Db')) {
            return ['html' => '', 'css' => ''];
        }
        $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $cond = "t.active = 1 AND t.entity_kind = 'page_template'";
        if ($sectionType !== null && $sectionType !== '') {
            $cond .= " AND (t.section_type = '" . pSQL($sectionType) . "' OR t.hook_name = '" . pSQL($hookName) . "')";
        } else {
            $cond .= " AND t.hook_name = '" . pSQL($hookName) . "'";
        }

        $rows = \Db::getInstance()->executeS(
            "SELECT t.id_template, tl.compiled_html, tl.compiled_css
             FROM `{$prefix}mb_template` t
             INNER JOIN `{$prefix}mb_template_lang` tl ON (t.id_template = tl.id_template AND tl.id_lang = " . (int) $idLang . ")
             WHERE {$cond}
             ORDER BY t.priority ASC, t.id_template DESC"
        );

        if (!is_array($rows) || count($rows) === 0) {
            return ['html' => '', 'css' => ''];
        }

        $htmlBlocks = [];
        $cssBlocks = [];
        $loader = static function (int $subId) use ($idLang): ?array {
            $sub = self::getTemplate($subId, $idLang);
            return $sub ? ['html' => (string) $sub['compiled_html'], 'css' => (string) $sub['compiled_css']] : null;
        };

        foreach ($rows as $r) {
            $htmlBlocks[] = DynamicTagResolver::resolve((string) ($r['compiled_html'] ?? ''), [], $loader);
            if (!empty($r['compiled_css'])) {
                $cssBlocks[] = (string) $r['compiled_css'];
            }
        }

        return [
            'html' => implode("\n", $htmlBlocks),
            'css'  => implode("\n", $cssBlocks),
        ];
    }
}
