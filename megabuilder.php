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
        $this->version = '1.2.0';
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
            // Versioned URLs so browsers fetch the new CSS/JS after a module upgrade
            // (PrestaShop's local asset paths cannot carry a query string, hence 'remote').
            $base = $this->_path . 'views/';
            $ver = '?v=' . rawurlencode($this->version);
            $this->context->controller->registerStylesheet(
                'module-megabuilder-storefront',
                $base . 'css/storefront.css' . $ver,
                ['media' => 'all', 'priority' => 150, 'server' => 'remote']
            );
            $this->context->controller->registerJavascript(
                'module-megabuilder-storefront-js',
                $base . 'js/storefront.js' . $ver,
                ['position' => 'bottom', 'priority' => 150, 'server' => 'remote']
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

        // These values go into a <script> block, so encode them as JS string literals.
        // HTML-escaping here would turn "&token=" into "&amp;token=" and break the admin token.
        $jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $jsUrl = json_encode($studioUrl, $jsFlags);
        $jsLabel = json_encode('✏️ Edit ' . ucfirst($targetSection) . ' Layout with Mega Builder', $jsFlags);

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (document.getElementById('mb-direct-launch-btn')) return;
  var toolbar = document.querySelector('.page-head .toolbarBox, .header-toolbar, .content-div .page-bar');
  var btn = document.createElement('a');
  btn.id = 'mb-direct-launch-btn';
  btn.href = {$jsUrl};
  btn.textContent = {$jsLabel};
  btn.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:8px 14px;margin:6px 12px;background:#2563eb;color:#fff;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;box-shadow:0 2px 6px rgba(37,99,235,0.25);';
  if (toolbar) {
    toolbar.prepend(btn);
  } else {
    // PrestaShop 9 product/category edit pages have no toolbar: float the button bottom-right instead.
    btn.style.position = 'fixed';
    btn.style.right = '16px';
    btn.style.bottom = '16px';
    btn.style.zIndex = '1050';
    document.body.appendChild(btn);
  }
});
</script>
HTML;
    }

    /** @var array{section: ?string, entity_id: int}|null */
    private ?array $pageContext = null;

    /**
     * Work out which of the 15 page sections the current storefront request is, plus the
     * product / category / CMS / brand id used by "specific IDs" targeting.
     *
     * @return array{section: ?string, entity_id: int}
     */
    private function getPageContext(): array
    {
        if ($this->pageContext !== null) {
            return $this->pageContext;
        }
        $phpSelf = (string) ($this->context->controller->php_self ?? '');
        $map = [
            'index'              => ['homepage', ''],
            'product'            => ['product', 'id_product'],
            'category'           => ['category', 'id_category'],
            'cms'                => ['cms', 'id_cms'],
            'my-account'         => ['account', ''],
            'identity'           => ['account', ''],
            'addresses'          => ['account', ''],
            'address'            => ['account', ''],
            'history'            => ['account', ''],
            'order-detail'       => ['account', ''],
            'order-slip'         => ['account', ''],
            'discount'           => ['account', ''],
            'guest-tracking'     => ['account', ''],
            'prices-drop'        => ['prices_drop', ''],
            'new-products'       => ['new_products', ''],
            'best-sales'         => ['best_sales', ''],
            'search'             => ['search', ''],
            'cart'               => ['checkout', ''],
            'order'              => ['checkout', ''],
            'order-confirmation' => ['checkout', ''],
            'manufacturer'       => ['manufacturers', 'id_manufacturer'],
            'supplier'           => ['manufacturers', 'id_supplier'],
            'pagenotfound'       => ['page_404', ''],
        ];
        [$section, $idParam] = $map[$phpSelf] ?? [null, ''];
        $entityId = $idParam !== '' ? (int) \Tools::getValue($idParam, 0) : 0;

        return $this->pageContext = ['section' => $section, 'entity_id' => $entityId];
    }

    /**
     * Render every published template attached to this hook that applies to the current page.
     */
    private function renderHookOutput(string $hookName): string
    {
        $idLang = (int) ($this->context->language->id ?? 1);
        $idShop = (int) ($this->context->shop->id ?? 1);
        $rendered = TemplateRepository::renderActiveForHook($hookName, $this->getPageContext(), $idLang, $idShop);
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
        return $this->renderHookOutput('displayNavFullWidth');
    }

    public function hookDisplayTop(): string
    {
        return $this->renderHookOutput('displayTop');
    }

    public function hookDisplayHome(): string
    {
        return $this->renderHookOutput('displayHome');
    }

    public function hookDisplayFooterProduct(): string
    {
        return $this->renderHookOutput('displayFooterProduct');
    }

    public function hookDisplayProductAdditionalInfo(): string
    {
        return $this->renderHookOutput('displayProductAdditionalInfo');
    }

    public function hookDisplayCategoryHeader(): string
    {
        return $this->renderHookOutput('displayCategoryHeader');
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
        return $this->renderHookOutput('displayFooter');
    }

    public function hookDisplayCustomerAccount(): string
    {
        return $this->renderHookOutput('displayCustomerAccount');
    }

    public function hookDisplayShoppingCartFooter(): string
    {
        return $this->renderHookOutput('displayShoppingCartFooter');
    }

    public function hookDisplayCMSDisputeInformation(): string
    {
        return $this->renderHookOutput('displayCMSDisputeInformation');
    }

    public function hookDisplayWrapperTop(): string
    {
        return $this->renderHookOutput('displayWrapperTop');
    }

    public function hookDisplayWrapperBottom(): string
    {
        return $this->renderHookOutput('displayWrapperBottom');
    }
}
