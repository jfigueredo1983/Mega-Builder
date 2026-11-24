<?php
/**
 * Mega Builder — Visual Drag-and-Drop Full-Site & Theme Builder for PrestaShop 8.0 - 9.2+
 *
 * @author    Mega Builder Architecture Team
 * @copyright 2026 Mega Builder
 * @license   AFL-3.0
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

use MegaBuilder\Repository\TemplateRepository;
use MegaBuilder\Security\HtmlSanitizer;

class MegaBuilder extends Module
{
    private const HOOKS = [
        'displayHeader',
        'displayNavFullWidth',
        'displayTop',
        'displayHome',
        'displayFooterProduct',
        'displayProductAdditionalInfo',
        'displayLeftColumn',
        'displayRightColumn',
        'displayFooter',
        'displayCustomerAccount',
        'displayShoppingCartFooter',
        'displayWrapperTop',
        'displayWrapperBottom',
        'displayCategoryHeader',
        'displayCMSDisputeInformation',
        'displayBackOfficeHeader',
        'actionFrontControllerSetMedia',
    ];

    public function __construct()
    {
        $this->name = 'megabuilder';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mega Builder Studio';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mega Builder — Visual Drag & Drop Page & Theme Studio');
        $this->description = $this->l('Full-site drag-and-drop visual page builder for PrestaShop 8.0 to 9.2+ with 77 widgets, 15 page template sections, 4 component sub-studios, and imperative 3-viewport responsiveness.');
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function install(): bool
    {
        $sqlOk = include __DIR__ . '/sql/install.php';
        if (!$sqlOk) {
            return false;
        }

        if (!parent::install()) {
            return false;
        }

        foreach (self::HOOKS as $hook) {
            $this->registerHook($hook);
        }

        $this->installAdminTab();
        TemplateRepository::ensureDefaultsSeeded(
            (int) ($this->context->language->id ?? 1),
            (int) ($this->context->shop->id ?? 1)
        );

        return true;
    }

    public function uninstall(): bool
    {
        include __DIR__ . '/sql/uninstall.php';
        $this->uninstallAdminTab();
        return parent::uninstall();
    }

    /**
     * Clicking "Configure" in Module Manager immediately opens the Visual Studio Dashboard (Zero Friction).
     */
    public function getContent(): void
    {
        \Tools::redirectAdmin($this->context->link->getAdminLink('AdminMegaBuilder', true));
    }

    private function installAdminTab(): bool
    {
        if (!class_exists('Tab')) {
            return true;
        }
        $idTab = (int) \Tab::getIdFromClassName('AdminMegaBuilder');
        if ($idTab > 0) {
            return true;
        }

        $tab = new \Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminMegaBuilder';
        $tab->module = $this->name;
        $parentId = (int) \Tab::getIdFromClassName('IMPROVE');
        if ($parentId <= 0) {
            $parentId = (int) \Tab::getIdFromClassName('AdminParentThemes');
        }
        $tab->id_parent = $parentId > 0 ? $parentId : 0;
        $tab->name = [];
        foreach (\Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Mega Builder Studio';
        }
        return (bool) $tab->add();
    }

    private function uninstallAdminTab(): bool
    {
        if (!class_exists('Tab')) {
            return true;
        }
        $idTab = (int) \Tab::getIdFromClassName('AdminMegaBuilder');
        if ($idTab > 0) {
            $tab = new \Tab($idTab);
            return (bool) $tab->delete();
        }
        return true;
    }

    public function hookActionFrontControllerSetMedia(): void
    {
        if (isset($this->context->controller) && method_exists($this->context->controller, 'registerStylesheet')) {
            $this->context->controller->registerStylesheet(
                'module-megabuilder-storefront',
                'modules/' . $this->name . '/views/css/storefront.css',
                ['media' => 'all', 'priority' => 150]
            );
            $this->context->controller->registerJavascript(
                'module-megabuilder-storefront-js',
                'modules/' . $this->name . '/views/js/storefront.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }
    }

    /**
     * Zero-Friction Back-Office Direct Entry Button ("Edit with Mega Builder") inside Product/Category/CMS forms.
     */
    public function hookDisplayBackOfficeHeader(): string
    {
        $controllerName = (string) \Tools::getValue('controller', '');
        if ($controllerName === 'AdminMegaBuilder') {
            return '';
        }

        $global = TemplateRepository::getGlobalSettings();
        if (empty($global['performance']['admin_direct_buttons'])) {
            return '';
        }

        $sectionMap = [
            'AdminProducts'   => 'product',
            'AdminCategories' => 'category',
            'AdminCmsContent' => 'cms',
        ];
        if (!isset($sectionMap[$controllerName])) {
            return '';
        }

        $targetSection = $sectionMap[$controllerName];
        $studioUrl = $this->context->link->getAdminLink('AdminMegaBuilder', true, [], [
            'mb_section' => $targetSection,
        ]);

        $safeUrl = HtmlSanitizer::escapeOutput($studioUrl);
        $safeSec = HtmlSanitizer::escapeOutput(ucfirst($targetSection));

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
  var toolbar = document.querySelector('.page-head .toolbarBox, .header-toolbar, .content-div .page-bar');
  if (!toolbar || document.getElementById('mb-direct-launch-btn')) return;
  var btn = document.createElement('a');
  btn.id = 'mb-direct-launch-btn';
  btn.href = '{$safeUrl}';
  btn.textContent = '✏️ Edit {$safeSec} Layout with Mega Builder';
  btn.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:8px 14px;margin:6px 12px;background:#2563eb;color:#fff;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;box-shadow:0 2px 6px rgba(37,99,235,0.25);';
  toolbar.prepend(btn);
});
</script>
HTML;
    }

    /**
     * Map native PrestaShop storefront controllers and hooks to our 15 Page Sections.
     */
    private function renderHookOutput(string $hookName, ?string $sectionType = null): string
    {
        $idLang = (int) ($this->context->language->id ?? 1);
        $rendered = TemplateRepository::renderActiveForHookOrSection($hookName, $sectionType, $idLang);
        if ($rendered['html'] === '') {
            return '';
        }

        $this->context->smarty->assign([
            'mb_hook_name'     => HtmlSanitizer::escapeOutput($hookName),
            'mb_rendered_html' => $rendered['html'],
            'mb_rendered_css'  => $rendered['css'],
        ]);

        return $this->fetch('module:megabuilder/views/templates/hook/storefront_section.tpl');
    }

    public function hookDisplayHeader(): string
    {
        return '';
    }

    public function hookDisplayNavFullWidth(): string
    {
        return $this->renderHookOutput('displayNavFullWidth', 'header');
    }

    public function hookDisplayTop(): string
    {
        return $this->renderHookOutput('displayTop');
    }

    public function hookDisplayHome(): string
    {
        return $this->renderHookOutput('displayHome', 'homepage');
    }

    public function hookDisplayFooterProduct(): string
    {
        return $this->renderHookOutput('displayFooterProduct', 'product');
    }

    public function hookDisplayProductAdditionalInfo(): string
    {
        return $this->renderHookOutput('displayProductAdditionalInfo');
    }

    public function hookDisplayCategoryHeader(): string
    {
        return $this->renderHookOutput('displayCategoryHeader', 'category');
    }

    public function hookDisplayLeftColumn(): string
    {
        return $this->renderHookOutput('displayLeftColumn');
    }

    public function hookDisplayRightColumn(): string
    {
        return $this->renderHookOutput('displayRightColumn');
    }

    public function hookDisplayFooter(): string
    {
        return $this->renderHookOutput('displayFooter', 'footer');
    }

    public function hookDisplayCustomerAccount(): string
    {
        return $this->renderHookOutput('displayCustomerAccount', 'account');
    }

    public function hookDisplayShoppingCartFooter(): string
    {
        return $this->renderHookOutput('displayShoppingCartFooter', 'checkout');
    }

    public function hookDisplayCMSDisputeInformation(): string
    {
        return $this->renderHookOutput('displayCMSDisputeInformation', 'cms');
    }

    public function hookDisplayWrapperTop(): string
    {
        $phpSelf = (string) ($this->context->controller->php_self ?? '');
        $controllerMap = [
            'prices-drop'  => 'prices_drop',
            'new-products' => 'new_products',
            'best-sales'   => 'best_sales',
            'search'       => 'search',
            'manufacturer' => 'manufacturers',
            'supplier'     => 'manufacturers',
            'pagenotfound' => 'page_404',
            '404'          => 'page_404',
        ];
        $sec = $controllerMap[$phpSelf] ?? null;
        return $this->renderHookOutput('displayWrapperTop', $sec);
    }

    public function hookDisplayWrapperBottom(): string
    {
        return $this->renderHookOutput('displayWrapperBottom');
    }
}
