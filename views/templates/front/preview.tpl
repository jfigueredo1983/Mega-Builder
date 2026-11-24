{extends file='page.tpl'}

{* page_title is also used for the document <title>, so it must stay plain text. *}
{block name='page_title'}{$mb_preview_title|escape:'html':'UTF-8'}{/block}

{block name='page_content'}
  <span class="mb-preview-pill">Mega Builder Live Storefront Preview: {$mb_preview_title|escape:'html':'UTF-8'}</span>
  {if !empty($mb_rendered_css)}
  <style id="mb-live-preview-css">
  {$mb_rendered_css nofilter}
  </style>
  {/if}
  <div id="mb-live-preview-stage" class="mb-preview-stage">
    {$mb_rendered_html nofilter}
  </div>
{/block}
