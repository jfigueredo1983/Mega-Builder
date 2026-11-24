{extends file='page.tpl'}

{block name='page_title'}
  <span class="mb-preview-pill">Mega Builder Live Storefront Preview: {$mb_preview_title}</span>
{/block}

{block name='page_content'}
  {if !empty($mb_rendered_css)}
  <style id="mb-live-preview-css">
  {$mb_rendered_css nofilter}
  </style>
  {/if}
  <div id="mb-live-preview-stage" class="mb-preview-stage">
    {$mb_rendered_html nofilter}
  </div>
{/block}
