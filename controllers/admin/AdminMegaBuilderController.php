<?php
/**
 * Mega Builder — Stage 4 & Stage 5 Back-Office Admin & JSON REST/RPC Controller
 * Compatible with PrestaShop 8.0 through 9.2+ (Symfony 4.4 - 6.4+ & Legacy Bridge).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

use MegaBuilder\Compiler\MegaMenuRenderer;
use MegaBuilder\Compiler\WidgetRegistry;
use MegaBuilder\Repository\TemplateRepository;
use MegaBuilder\Security\HtmlSanitizer;
use MegaBuilder\Security\PreviewToken;
use MegaBuilder\Service\MediaManagerService;
use MegaBuilder\Validator\PayloadValidator;

class AdminMegaBuilderController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * API action => PrestaShop profile permission required (view / add / edit / delete).
     * Any action not listed here is rejected.
     */
    private const ACTION_PERMISSIONS = [
        'listTemplates'      => 'view',
        'getTemplate'        => 'view',
        'previewTemplate'    => 'view',
        'getPreviewLink'     => 'view',
        'listBlueprints'     => 'view',
        'getGlobalSettings'  => 'view',
        'listMedia'          => 'view',
        'exportTemplates'    => 'view',
        'saveTemplate'       => 'edit', // upgraded to 'add' below when creating
        'toggleTemplateStatus' => 'edit',
        'restoreRevision'    => 'edit',
        'saveGlobalSettings' => 'edit',
        'saveStudioTheme'    => 'edit',
        'duplicateTemplate'  => 'add',
        'importTemplates'    => 'add',
        'uploadMedia'        => 'add',
        'createMediaFolder'  => 'add',
        'deleteTemplate'     => 'delete',
        'deleteMedia'        => 'delete',
    ];

    /**
     * CSRF token bound to this employee and their password hash, signed with the install secret.
     * Stable across the session (no midnight rotation); invalidated when the password changes.
     * Returns '' if no install secret exists, which makes every API call fail closed.
     */
    public function getCsrfToken(): string
    {
        $secret = PreviewToken::serverSecret();
        $employee = $this->context->employee ?? null;
        $empId = (int) ($employee->id ?? 0);
        if ($secret === '' || $empId <= 0) {
            return '';
        }
        $passwdHash = (string) ($employee->passwd ?? '');
        return hash_hmac('sha256', 'mb_csrf|' . $empId . '|' . $passwdHash, $secret);
    }

    /**
     * Check the current employee's profile permission for this controller's tab.
     */
    private function hasPermission(string $level): bool
    {
        if (!method_exists($this, 'access')) {
            return true; // Very old core without per-tab access API.
        }
        return (bool) $this->access($level);
    }

    public function initContent(): void
    {
        if (\Tools::getValue('ajax') || \Tools::getValue('mb_action')) {
            $this->dispatchAjaxAction();
            return;
        }

        $idLang = (int) ($this->context->language->id ?? 1);
        $idShop = (int) ($this->context->shop->id ?? 1);
        TemplateRepository::ensureDefaultsSeeded($idLang, $idShop);

        // Auto-seed starter showcase templates as Draft (active = 0) if the DB is completely empty
        $existing = TemplateRepository::listTemplates(null, null, $idLang, $idShop);
        if (count($existing) === 0) {
            $this->seedInitialDraftShowcases($idLang, $idShop);
            $existing = TemplateRepository::listTemplates(null, null, $idLang, $idShop);
        }

        $ajaxBaseUrl = $this->context->link->getAdminLink('AdminMegaBuilder', true);
        // Unsigned base URL; per-template signed links come from the getPreviewLink action.
        $previewBaseUrl = $this->context->link->getModuleLink('megabuilder', 'preview');

        $bootPayload = [
            'version'         => (string) ($this->module->version ?? '1.2.0'),
            'psVersion'       => defined('_PS_VERSION_') ? _PS_VERSION_ : '9.2.0',
            'shopName'        => (string) ($this->context->shop->name ?? 'DoIt Furniture'),
            'storefrontUrl'   => $this->context->shop->getBaseURL(true),
            'ajaxUrl'         => $ajaxBaseUrl,
            'previewUrl'      => $previewBaseUrl,
            'csrfToken'       => $this->getCsrfToken(),
            'idLang'          => $idLang,
            'idShop'          => $idShop,
            'initialSection'  => HtmlSanitizer::sanitizePlainText(\Tools::getValue('mb_section', ''), 48),
            'initialTemplate' => (int) \Tools::getValue('id_template', 0),
            'pageSections'    => PayloadValidator::PAGE_SECTIONS,
            'subComponents'   => PayloadValidator::SUB_COMPONENTS,
            'allowedHooks'    => PayloadValidator::ALLOWED_HOOKS,
            'widgets'         => WidgetRegistry::getAllWidgets(),
            'blueprints'      => WidgetRegistry::getStarterBlueprints(),
            'templates'       => $existing,
            'globalSettings'  => TemplateRepository::getGlobalSettings(),
            'studioTheme'     => TemplateRepository::getStudioTheme(),
            'media'           => MediaManagerService::listMedia(),
            'categoryMenu'    => MegaMenuRenderer::loadCategoryItems($idLang, $idShop, MegaMenuRenderer::MAX_ITEMS),
        ];

        $modulePath = _MODULE_DIR_ . 'megabuilder/';
        $distVer = (string) (@filemtime(dirname(__DIR__, 2) . '/views/dist/studio.js') ?: time());

        $this->context->smarty->assign([
            'mb_boot_json'   => json_encode($bootPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
            'mb_studio_js'   => $modulePath . 'views/dist/studio.js?v=' . $distVer,
            'mb_studio_css'  => $modulePath . 'views/dist/studio.css?v=' . $distVer,
        ]);

        $tplPath = dirname(__DIR__, 2) . '/views/templates/admin/studio_host.tpl';
        $this->content .= $this->context->smarty->fetch($tplPath);

        parent::initContent();
    }

    /**
     * Seed initial Draft templates (active = 0) so first-time merchants immediately see rich examples.
     */
    private function seedInitialDraftShowcases(int $idLang, int $idShop): void
    {
        $empId = (int) ($this->context->employee->id ?? 1);
        $blueprints = WidgetRegistry::getStarterBlueprints();
        $starterKeys = ['megamenu', 'slider', 'card', 'accordion', 'header', 'homepage', 'product', 'category', 'footer'];

        foreach ($starterKeys as $key) {
            if (!isset($blueprints[$key])) {
                continue;
            }
            $bp = $blueprints[$key];
            TemplateRepository::saveTemplate([
                'id_template'    => 0,
                'entity_kind'    => $bp['entity_kind'],
                'section_type'   => $bp['section_type'],
                'hook_name'      => $bp['hook_name'],
                'scope_rule'     => 'all',
                'scope_targets'  => [],
                'active'         => 0, // Always Draft on install for zero-friction non-destructive safety
                'priority'       => 10,
                'title'          => $bp['title'],
                'layout'         => $bp['layout'],
                'revision_label' => 'Initial Starter Blueprint',
            ], $idLang, $idShop, $empId);
        }
    }

    /**
     * Dispatch Stage 4 JSON REST/RPC actions with Stage 5 CSRF & RBAC enforcement.
     */
    private function dispatchAjaxAction(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $rawInput = (string) file_get_contents('php://input');
        $body = [];
        if ($rawInput !== '') {
            try {
                $decoded = json_decode($rawInput, true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            } catch (\Throwable) {
                $this->sendJson(400, false, 'INVALID_JSON', 'Malformed JSON request payload.');
                return;
            }
        }

        $action = (string) ($body['action'] ?? \Tools::getValue('mb_action', \Tools::getValue('action', '')));
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!isset(self::ACTION_PERMISSIONS[$action])) {
            $this->sendJson(400, false, 'UNKNOWN_ACTION', 'Unknown API action.');
            return;
        }

        // Shield 1: every API call must be a POST carrying the CSRF header (the studio always sends both).
        $expectedCsrf = $this->getCsrfToken();
        $sentCsrf = (string) ($_SERVER['HTTP_X_MB_CSRF_TOKEN'] ?? '');
        if ($method !== 'POST' || $expectedCsrf === '' || $sentCsrf === '' || !hash_equals($expectedCsrf, $sentCsrf)) {
            $this->sendJson(403, false, 'CSRF_INVALID', 'Invalid or missing X-MB-CSRF-Token security header.');
            return;
        }

        // Role-based access: map the action to the employee profile's view/add/edit/delete rights.
        $required = self::ACTION_PERMISSIONS[$action];
        if ($action === 'saveTemplate' && (int) ($body['id_template'] ?? 0) <= 0) {
            $required = 'add';
        }
        if (!$this->hasPermission($required)) {
            $this->sendJson(403, false, 'PERMISSION_DENIED', "Your employee profile lacks '{$required}' permission for Mega Builder.");
            return;
        }

        $idLang = (int) ($this->context->language->id ?? 1);
        $idShop = (int) ($this->context->shop->id ?? 1);
        $idEmployee = (int) ($this->context->employee->id ?? 0);

        switch ($action) {
            case 'listTemplates':
                $kind = isset($body['entity_kind']) ? (string) $body['entity_kind'] : (string) \Tools::getValue('entity_kind', '');
                $sec = isset($body['section_type']) ? (string) $body['section_type'] : (string) \Tools::getValue('section_type', '');
                $list = TemplateRepository::listTemplates($kind ?: null, $sec ?: null, $idLang, $idShop);
                $this->sendJson(200, true, 'TEMPLATES_LISTED', 'Templates retrieved.', ['templates' => $list]);
                return;

            case 'getTemplate':
                $idTpl = (int) ($body['id_template'] ?? \Tools::getValue('id_template', 0));
                $tpl = TemplateRepository::getTemplate($idTpl, $idLang);
                if (!$tpl) {
                    $this->sendJson(404, false, 'NOT_FOUND', 'Template not found.');
                    return;
                }
                $this->sendJson(200, true, 'TEMPLATE_LOADED', 'Template loaded.', ['template' => $tpl]);
                return;

            case 'saveTemplate':
                $res = TemplateRepository::saveTemplate($body, $idLang, $idShop, $idEmployee);
                if (!$res['ok']) {
                    $this->sendJson(422, false, 'VALIDATION_ERROR', 'Template validation failed.', $res['data'], $res['errors']);
                    return;
                }
                $this->sendJson(200, true, 'TEMPLATE_SAVED', 'Template validated, compiled, and saved.', [
                    'template'  => $res['data'],
                    'templates' => TemplateRepository::listTemplates(null, null, $idLang, $idShop),
                ]);
                return;

            case 'toggleTemplateStatus':
                $idTpl = (int) ($body['id_template'] ?? 0);
                $active = (int) ($body['active'] ?? 0);
                $ok = TemplateRepository::toggleStatus($idTpl, $active, $idShop);
                $this->sendJson($ok ? 200 : 404, $ok, $ok ? 'STATUS_UPDATED' : 'NOT_FOUND', $ok ? 'Template status updated.' : 'Template not found.', [
                    'id_template' => $idTpl,
                    'active'      => $active ? 1 : 0,
                    'templates'   => TemplateRepository::listTemplates(null, null, $idLang, $idShop),
                ]);
                return;

            case 'duplicateTemplate':
                $idTpl = (int) ($body['id_template'] ?? 0);
                $copy = TemplateRepository::duplicateTemplate($idTpl, $idLang, $idShop, $idEmployee);
                if (!$copy) {
                    $this->sendJson(404, false, 'NOT_FOUND', 'Source template not found.');
                    return;
                }
                $this->sendJson(200, true, 'TEMPLATE_DUPLICATED', 'Template duplicated as Draft.', [
                    'template'  => $copy,
                    'templates' => TemplateRepository::listTemplates(null, null, $idLang, $idShop),
                ]);
                return;

            case 'deleteTemplate':
                $idTpl = (int) ($body['id_template'] ?? 0);
                $ok = TemplateRepository::deleteTemplate($idTpl);
                $this->sendJson($ok ? 200 : 404, $ok, $ok ? 'TEMPLATE_DELETED' : 'NOT_FOUND', $ok ? 'Template deleted.' : 'Template not found.', [
                    'id_template' => $idTpl,
                    'templates'   => TemplateRepository::listTemplates(null, null, $idLang, $idShop),
                ]);
                return;

            case 'restoreRevision':
                $idTpl = (int) ($body['id_template'] ?? 0);
                $idRev = (int) ($body['id_revision'] ?? 0);
                $restored = TemplateRepository::restoreRevision($idTpl, $idRev, $idLang, $idShop, $idEmployee);
                if (!$restored) {
                    $this->sendJson(404, false, 'REVISION_NOT_FOUND', 'Revision snapshot not found.');
                    return;
                }
                $this->sendJson(200, true, 'REVISION_RESTORED', 'Revision restored.', ['template' => $restored]);
                return;

            case 'previewTemplate':
                $rawLayout = is_array($body['layout'] ?? null) ? $body['layout'] : [];
                $rendered = TemplateRepository::renderLiveLayout($rawLayout, $idLang);
                $this->sendJson(200, true, 'PREVIEW_RENDERED', 'Live storefront preview compiled.', $rendered);
                return;

            case 'getPreviewLink':
                $idTpl = (int) ($body['id_template'] ?? 0);
                if (!TemplateRepository::getTemplate($idTpl, $idLang)) {
                    $this->sendJson(404, false, 'NOT_FOUND', 'Template not found.');
                    return;
                }
                $token = PreviewToken::create($idTpl);
                if ($token === '') {
                    $this->sendJson(500, false, 'NO_SECRET', 'Server secret unavailable; cannot sign preview link.');
                    return;
                }
                $this->sendJson(200, true, 'PREVIEW_LINK', 'Signed preview link valid for 15 minutes.', [
                    'url'        => $this->context->link->getModuleLink('megabuilder', 'preview', [
                        'id_template' => $idTpl,
                        'mb_token'    => $token,
                    ]),
                    'expires_in' => PreviewToken::DEFAULT_TTL_SECONDS,
                ]);
                return;

            case 'listBlueprints':
                $this->sendJson(200, true, 'BLUEPRINTS_LISTED', 'Starter blueprints loaded.', [
                    'blueprints' => WidgetRegistry::getStarterBlueprints(),
                ]);
                return;

            case 'getGlobalSettings':
                $this->sendJson(200, true, 'SETTINGS_LOADED', 'Global settings loaded.', [
                    'globalSettings' => TemplateRepository::getGlobalSettings(),
                    'studioTheme'    => TemplateRepository::getStudioTheme(),
                ]);
                return;

            case 'saveGlobalSettings':
                $settings = is_array($body['globalSettings'] ?? null) ? $body['globalSettings'] : $body;
                $saved = TemplateRepository::saveGlobalSettings($settings);
                $this->sendJson(200, true, 'SETTINGS_SAVED', 'Global design system settings saved.', [
                    'globalSettings' => $saved,
                ]);
                return;

            case 'saveStudioTheme':
                $theme = is_array($body['studioTheme'] ?? null) ? $body['studioTheme'] : $body;
                $saved = TemplateRepository::saveStudioTheme($theme);
                $this->sendJson(200, true, 'THEME_SAVED', 'Studio UI theme saved.', [
                    'studioTheme' => $saved,
                ]);
                return;

            case 'listMedia':
                $folder = (string) ($body['folder'] ?? \Tools::getValue('folder', ''));
                $search = (string) ($body['search'] ?? \Tools::getValue('search', ''));
                $this->sendJson(200, true, 'MEDIA_LISTED', 'Media library loaded.', MediaManagerService::listMedia($folder, $search));
                return;

            case 'createMediaFolder':
                $folder = (string) ($body['folder'] ?? '');
                $res = MediaManagerService::createFolder($folder);
                $this->sendJson($res['ok'] ? 200 : 422, $res['ok'], $res['ok'] ? 'FOLDER_CREATED' : 'INVALID_FOLDER', $res['message'], MediaManagerService::listMedia($res['folder']));
                return;

            case 'uploadMedia':
                $folder = (string) ($body['folder'] ?? \Tools::getValue('folder', ''));
                $filename = (string) ($body['filename'] ?? 'upload.svg');
                $content = (string) ($body['content'] ?? '');

                if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                    $filename = (string) $_FILES['file']['name'];
                    $content = (string) file_get_contents($_FILES['file']['tmp_name']);
                } elseif (str_starts_with($content, 'data:')) {
                    $parts = explode(',', $content, 2);
                    $content = isset($parts[1]) ? (string) base64_decode($parts[1], true) : '';
                }

                $res = MediaManagerService::saveMediaContent($filename, $content, $folder);
                $this->sendJson($res['ok'] ? 200 : 422, $res['ok'], $res['ok'] ? 'MEDIA_UPLOADED' : 'UPLOAD_REJECTED', $res['message'], [
                    'uploaded' => $res['file'] ?? null,
                    'media'    => MediaManagerService::listMedia($folder),
                ]);
                return;

            case 'deleteMedia':
                $relPath = (string) ($body['rel_path'] ?? '');
                $folder = (string) ($body['folder'] ?? '');
                $ok = MediaManagerService::deleteMedia($relPath);
                $this->sendJson($ok ? 200 : 404, $ok, $ok ? 'MEDIA_DELETED' : 'NOT_FOUND', $ok ? 'Media file removed.' : 'File not found.', MediaManagerService::listMedia($folder));
                return;

            case 'exportTemplates':
                $all = TemplateRepository::listTemplates(null, null, $idLang, $idShop);
                $fullTemplates = [];
                foreach ($all as $item) {
                    $full = TemplateRepository::getTemplate((int) $item['id_template'], $idLang);
                    if ($full) {
                        $fullTemplates[] = $full;
                    }
                }
                $this->sendJson(200, true, 'EXPORT_READY', 'Templates bundle exported.', [
                    'bundle' => [
                        'exporter'       => 'MegaBuilder 1.0',
                        'exported_at'    => gmdate('c'),
                        'globalSettings' => TemplateRepository::getGlobalSettings(),
                        'studioTheme'    => TemplateRepository::getStudioTheme(),
                        'templates'      => $fullTemplates,
                    ],
                ]);
                return;

            case 'importTemplates':
                $bundle = is_array($body['bundle'] ?? null) ? $body['bundle'] : [];
                $importedCount = 0;
                if (is_array($bundle['globalSettings'] ?? null)) {
                    TemplateRepository::saveGlobalSettings($bundle['globalSettings']);
                }
                if (is_array($bundle['templates'] ?? null)) {
                    foreach (array_slice($bundle['templates'], 0, 50) as $t) {
                        if (!is_array($t)) {
                            continue;
                        }
                        $t['id_template'] = 0;
                        $t['active'] = 0; // Imported templates always start safely as Draft
                        $saved = TemplateRepository::saveTemplate($t, $idLang, $idShop, $idEmployee);
                        if ($saved['ok']) {
                            $importedCount++;
                        }
                    }
                }
                $this->sendJson(200, true, 'IMPORT_COMPLETE', "Imported {$importedCount} templates as Draft.", [
                    'imported_count' => $importedCount,
                    'templates'      => TemplateRepository::listTemplates(null, null, $idLang, $idShop),
                    'globalSettings' => TemplateRepository::getGlobalSettings(),
                ]);
                return;

            default:
                $this->sendJson(400, false, 'UNKNOWN_ACTION', "Unknown API action '{$action}'.");
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $errors
     */
    private function sendJson(int $httpCode, bool $ok, string $code, string $message, array $data = [], array $errors = []): void
    {
        http_response_code($httpCode);
        echo json_encode([
            'ok'      => $ok,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
