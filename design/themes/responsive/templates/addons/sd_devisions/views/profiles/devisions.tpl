{if $devisions}
    {script src="js/tygh/exceptions.js"}

    {if !$show_empty}
        {split data=$devisions size=$columns|default:"2" assign="splitted_devisions"}
    {else}
        {split data=$devisions size=$columns|default:"2" assign="splitted_devisions" skip_complete=true}
    {/if}

    {math equation="100 / x" x=$columns|default:"2" assign="cell_width"}

    {* FIXME: Don't move this file *}

    <div class="grid-list">
        {script src="js/tygh/product_image_gallery.js"}
        {if !$no_pagination}
            {include file="common/pagination.tpl"}
        {/if}
        {strip}
            {foreach from=$splitted_devisions item="sdevisions"}
                {foreach from=$sdevisions item="devision"}
                    <div class="ty-column{$columns}">
                        {if $devision}
                            {assign var="obj_id" value=$devision.devision_id}
                            {assign var="obj_id_prefix" value="`$obj_prefix``$devision.devision_id`"}

                            <div class="ty-grid-list__item ty-quick-view-button__wrapper">
                                <div class="ty-grid-list__image">
                                        <a href="{"profiles.devision?devision_id={$devision.devision_id}"|fn_url}" class="product-title" title={$devision.devision}>{include file="common/image.tpl" 
                                            no_ids=true 
                                            images=$devision.main_pair
                                            image_width=$settings.Thumbnails.product_lists_thumbnail_width 
                                            image_height=$settings.Thumbnails.product_lists_thumbnail_height 
                                            }
                                        </a>
                                </div>

                                <div class="ty-grid-list__item-name">
                                    <bdi>
                                        <a href="{"profiles.devision?devision_id={$devision.devision_id}"|fn_url}" class="product-title" title={$devision.devision}>{$devision.devision}</a>
                                    </bdi>
                                </div>
                                <div>            
                                    <p title={$devision.manager.email}>
                                        {$devision.manager.firstname} {$devision.manager.lastname}
                                    </p>                       
                                </div>
                            </div>
                        {/if}
                    </div>
                {/foreach}

            {/foreach}
        {/strip}
        {script src="js/tygh/product_image_gallery.js"}
        {if !$no_pagination}
            {include file="common/pagination.tpl"}
        {/if}
    </div>
{/if}

{capture name="mainbox_title"}{$title}{/capture}