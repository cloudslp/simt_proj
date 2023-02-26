<div id="product_features_{$block.block_id}">
<div class="ty-feature">
    {if $devision_data.main_pair}
    <div class="ty-feature__image">
        {include file="common/image.tpl" images=$devision_data.main_pair image_height=250}
    </div>
    {/if}
    
    <div class="ty-feature__description ty-wysiwyg-content">
        {$devision_data.description nofilter}
    </div>
</div>
<div>   
        <h2><label class="ty-control-group__label" id="sku_9">{__("manager")}</label></h2>
        <ul class="ty-control-group ty-sku-item cm-hidden-wrapper" id="sku_update_9">
            <li class="ty-control-group__item cm-reload-9" id="product_code_9">{$devision_data.manager.firstname} {$devision_data.manager.lastname}<strong>({$devision_data.manager.email})</strong></li>
        </ul>
    </div>

    <div>
        <h2><label class="ty-control-group__label" id="sku_9">{__("user")}</label></h2>
        <ul class="ty-control-group ty-sku-item cm-hidden-wrapper" id="sku_update_9">
            {foreach from=$users item=user}
                            <li id="product_code_9">{$user.firstname} {$user.lastname} <strong>({$user.email})</strong></li>
            {/foreach}
        </ul>
    </div>

    <!--product_features_{$block.block_id}-->
</div>
{capture name="mainbox_title"}{$devision_data.variant nofilter}{/capture}