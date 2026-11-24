<?php
/**
 * Mega Builder — Storefront Live Preview Front Controller
 * Renders a Draft or Active template inside the real storefront layout.
 * Access requires a signed, expiring token (?id_template=N&mb_token=...) issued
 * to an authenticated Back-Office employee via the getPreviewLink API action.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

use MegaBuilder\Repository\TemplateRepository;
use MegaBuilder\Security\PreviewToken;

class MegaBuilderPreviewModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        $idLang = (int) ($this->context->language->id ?? 1);
        $idTemplate = (int) \Tools::getValue('id_template', 0);
        $token = (string) \Tools::getValue('mb_token', '');

        $tpl = null;
        if ($idTemplate > 0 && PreviewToken::verify($idTemplate, $token)) {
            $tpl = TemplateRepository::getTemplate($idTemplate, $idLang);
        }

        if (!$tpl) {
            // Same response for bad token and missing template: do not reveal which ids exist.
            header('X-Robots-Tag: noindex, nofollow');
            \Tools::redirect('index.php?controller=404');
            return;
        }

        parent::initContent();

        // Clickjacking protection allowing same-origin Back-Office Studio iframe embedding
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, private');

        $rendered = TemplateRepository::renderLiveLayout(is_array($tpl['layout']) ? $tpl['layout'] : [], $idLang);

        $this->context->smarty->assign([
            // Plain text: the template escapes it once (it is also reused in the <title> tag).
            'mb_preview_title' => (string) $tpl['title'],
            'mb_rendered_html' => $rendered['html'],
            'mb_rendered_css'  => $rendered['css'],
        ]);

        $this->setTemplate('module:megabuilder/views/templates/front/preview.tpl');
    }
}
