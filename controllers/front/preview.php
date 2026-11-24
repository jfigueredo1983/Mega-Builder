<?php
/**
 * Mega Builder — Stage 4 & Stage 5 Storefront Live Preview Front Controller
 * Renders any Draft or Active template inside the real PrestaShop storefront layout
 * with SAMEORIGIN framing policy.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

use MegaBuilder\Compiler\WidgetRegistry;
use MegaBuilder\Repository\TemplateRepository;
use MegaBuilder\Security\HtmlSanitizer;

class MegaBuilderPreviewModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        parent::initContent();

        // Shield 5: Clickjacking protection allowing same-origin Back-Office Studio iframe embedding
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");

        $idLang = (int) ($this->context->language->id ?? 1);
        $idTemplate = (int) \Tools::getValue('id_template', 0);
        $sectionType = HtmlSanitizer::sanitizePlainText(\Tools::getValue('section_type', 'homepage'), 48);

        $rendered = ['html' => '', 'css' => ''];
        $title = 'Mega Builder Storefront Preview';

        if ($idTemplate > 0) {
            $tpl = TemplateRepository::getTemplate($idTemplate, $idLang);
            if ($tpl) {
                $title = (string) $tpl['title'];
                $rendered = TemplateRepository::renderLiveLayout(is_array($tpl['layout']) ? $tpl['layout'] : []);
            }
        }

        if ($rendered['html'] === '') {
            $bps = WidgetRegistry::getStarterBlueprints();
            $bp = $bps[$sectionType] ?? $bps['homepage'];
            $title = (string) ($bp['title'] ?? 'Storefront Preview');
            $rendered = TemplateRepository::renderLiveLayout($bp['layout'] ?? []);
        }

        $this->context->smarty->assign([
            'mb_preview_title' => HtmlSanitizer::escapeOutput($title),
            'mb_rendered_html' => $rendered['html'],
            'mb_rendered_css'  => $rendered['css'],
        ]);

        $this->setTemplate('module:megabuilder/views/templates/front/preview.tpl');
    }
}
