{* Mega Builder — Storefront Hook Output *}
{if !empty($mb_rendered_css)}
<style data-mb-hook="{$mb_hook_name|escape:'html':'UTF-8'}">
{$mb_rendered_css nofilter}
</style>
{/if}
<div class="mb-hook-wrapper" data-mb-hook="{$mb_hook_name|escape:'html':'UTF-8'}">
  {$mb_rendered_html nofilter}
</div>
