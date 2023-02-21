{** devisions section **}

{capture name="mainbox"}
    <form action="{""|fn_url}" method="post" id="devisions_form" name="devisions_form" enctype="multipart/form-data">
        <input type="hidden" name="fake" value="1" />
        {include file="common/pagination.tpl" save_current_page=true save_current_url=true div_id="pagination_contents_devisions"}

        {$c_url=$config.current_url|fn_query_remove:"sort_by":"sort_order"}

        {$rev=$smarty.request.content_id|default:"pagination_contents_devisions"}
        {include_ext file="common/icon.tpl" class="icon-`$search.sort_order_rev`" assign=c_icon}
        {include_ext file="common/icon.tpl" class="icon-dummy" assign=c_dummy}
        {$devision_statuses=""|fn_get_default_statuses:true}
        {$has_permission = fn_check_permissions("devisions", "update_status", "admin", "POST")}

        {if $devisions}
            {capture name="devisions_table"}
                <div class="table-responsive-wrapper longtap-selection">
                    <table class="table table-middle table--relative table-responsive">
                        <thead
                            data-ca-bulkedit-default-object="true"
                            data-ca-bulkedit-component="defaultObject"
                        >
                        <tr>
                            <th width="6%" class="left mobile-hide">
                                {include file="common/check_items.tpl" is_check_disabled=!$has_permission check_statuses=($has_permission) ? $devision_statuses : '' }

                                <input type="checkbox"
                                    class="bulkedit-toggler hide"
                                    data-ca-bulkedit-disable="[data-ca-bulkedit-default-object=true]"
                                    data-ca-bulkedit-enable="[data-ca-bulkedit-expanded-object=true]"
                                />
                            </th>
                            <th width="25%">{__("image")}</th>
                            <th><a class="cm-ajax" href="{"`$c_url`&sort_by=name&sort_order=`$search.sort_order_rev`"|fn_url}" data-ca-target-id={$rev}>{__("devision")}{if $search.sort_by === "name"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a></th>
                            <th width="15%"><a class="cm-ajax" href="{"`$c_url`&sort_by=timestamp&sort_order=`$search.sort_order_rev`"|fn_url}" data-ca-target-id={$rev}>{__("creation_date")}{if $search.sort_by === "timestamp"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a></th>
                            <th width="6%" class="mobile-hide">&nbsp;</th>
                            <th width="10%" class="right"><a class="cm-ajax" href="{"`$c_url`&sort_by=status&sort_order=`$search.sort_order_rev`"|fn_url}" data-ca-target-id={$rev}>{__("status")}{if $search.sort_by === "status"}{$c_icon nofilter}{/if}</a></th>
                        </tr>
                        </thead>
                        {foreach from=$devisions item=devision}
                            <tr class="cm-row-status-{$devision.status|lower} cm-longtap-target"
                                {if $has_permission}
                                    data-ca-longtap-action="setCheckBox"
                                    data-ca-longtap-target="input.cm-item"
                                    data-ca-id="{$devision.devision_id}"
                                {/if}
                            >
                                {$allow_save=true}

                                {if $allow_save}
                                    {$no_hide_input="cm-no-hide-input"}
                                {else}
                                    {$no_hide_input=""}
                                {/if}

                                <td width="6%" class="left mobile-hide">
                                    <input type="checkbox" name="devision_ids[]" value="{$devision.devision_id}" class="cm-item {$no_hide_input} cm-item-status-{$devision.status|lower}" /></td>
                                <td width="25%" class="products-list__image">
                                    {include
                                            file="common/image.tpl"
                                            image=$devision.main_pair
                                            image_id=$devision_data.main_pair.image_id
                                            image_width="100%"
                                            image_height=$image_height
                                            href="profiles.update_devision?devision_id=`$devision.devision_id`"|fn_url
                                            image_css_class="products-list__image--img"
                                            link_css_class="products-list__image--link"
                                            lazy_load=true
                                    }
                                </td>
                                <td class="{$no_hide_input}" data-th="{__("name")}">
                                    <a class="row-status" href="{"profiles.update_devision?devision_id=`$devision.devision_id`"|fn_url}">{$devision.devision}</a>
                                </td>

                                <td width="15%" data-th="{__("creation_date")}">
                                    {$devision.timestamp|date_format:"`$settings.Appearance.date_format`, `$settings.Appearance.time_format`"}
                                </td>

                                <td width="6%" class="mobile-hide">
                                    {capture name="tools_list"}
                                            <li>{btn type="list" text=__("edit") href="profiles.update_devision?devision_id=`$devision.devision_id`"}</li>
                                        {if $allow_save}
                                            <li>{btn type="list" class="cm-confirm" text=__("delete") href="profiles.delete_devision?devision_id=`$devision.devision_id`" method="POST"}</li>
                                        {/if}
                                    {/capture}
                                    <div class="hidden-tools">
                                        {dropdown content=$smarty.capture.tools_list}
                                    </div>
                                </td>

                                <td width="10%" class="right" data-th="{__("status")}">
                                    {include file="common/select_popup.tpl" 
                                        id=$devision.devision_id 
                                        status=$devision.status 
                                        hidden=true 
                                        object_id_name="devision_id" 
                                        table="devisions" 
                                        popup_additional_class="`$no_hide_input` 
                                        dropleft"
                                    }
                                </td>
                            </tr>
                        {/foreach}
                    </table>
                </div>
            {/capture}

            {include file="common/context_menu_wrapper.tpl"
                form="devisions_form"
                object="devisions"
                items=$smarty.capture.devisions_table
                has_permissions=$has_permission
            }
        {else}
            <p class="no-items">{__("no_data")}</p>
        {/if}

        {include file="common/pagination.tpl" div_id="pagination_contents_devisions"}

        {capture name="adv_buttons"}
            {hook name="devisions:adv_buttons"}
                {include file="common/tools.tpl" 
                    tool_href="profiles.add_devision" 
                    prefix="top" hide_tools="true" 
                    title=__("add_devision") 
                    icon="icon-plus"
                }
            {/hook}
        {/capture}
    </form>
{/capture}

{capture name="sidebar"}
    {hook name="devisions:manage_sidebar"}
        {include file="common/saved_search.tpl" dispatch="profiles.manage_devisions" view_type="banners"}
        {include file="addons/sd_devisions/views/profiles/components/search.tpl" dispatch="profiles.manage_devisions"}
    {/hook}
{/capture}

{hook name="devisions:manage_mainbox_params"}
    {$page_title = __("devisions")}
    {$select_languages = true}
{/hook}

{include 
    file="common/mainbox.tpl" 
    title=$page_title 
    content=$smarty.capture.mainbox 
    adv_buttons=$smarty.capture.adv_buttons 
    select_languages=$select_languages 
    sidebar=$smarty.capture.sidebar
}

{** ad section **}