{if $devision_data}
    {assign var="id" value=$devision_data.devision_id}
{else}
    {assign var="id" value=0}
{/if}



{$allow_save = $devision|fn_allow_save_object:"devisions"}
{$hide_inputs = ""|fn_check_form_permissions}
{assign var="b_type" value=$devision.type|default:"G"}

{capture name="mainbox"}

    <form action="{""|fn_url}" method="post" class="form-horizontal form-edit{if !$allow_save || $hide_inputs} cm-hide-inputs{/if}" name="devisions_form" enctype="multipart/form-data">
        <input type="hidden" class="cm-no-hide-input" name="fake" value="1" />
        <input type="hidden" class="cm-no-hide-input" name="devision_id" value="{$id}" />

        

            <div id="content_general">
                <div class="control-group">
                    <label for="elm_devision_name" class="control-label cm-required">{__("name")}</label>
                    <div class="controls">
                    <input type="text" name="devision_data[devision]" id="elm_devision_name" value="{$devision_data.devision}" size="25" class="input-large" /></div>
                </div>

                <div class="control-group" id="devision_graphic">
                    <label class="control-label">{__("image")}</label>
                    <div class="controls">
                        {include file="common/attach_images.tpl"
                            image_name="devision"
                            image_object_type="devision"
                            image_pair=$devision_data.main_pair
                            image_object_id=$id
                            no_detailed=true
                            hide_titles=true
                        }
                    </div>
                </div>

                <div class="control-group">
                    <label class="control-label">{("manager")}</label>
                    <div class="controls">
                        {include 
                            file="pickers/users/picker.tpl" 
                            but_text=("add_manager") 
                            data_id="return_users" but_meta="btn" 
                            input_name="devision_data[manager_id]" 
                            item_ids=$devision_data.manager_id
                            placement="right"
                            display=radio
                            view_mode="single_button"
                            user_info=$manager_info
                        }
                    </div>
                </div>

                <div class="control-group">
                    <label class="control-label">{("employees")}</label>
                    <div class="controls">
                        {include 
                            file="pickers/users/picker.tpl" 
                            but_text=("add_employees") 
                            data_id="return_users" but_meta="btn" 
                            input_name="devision_data[users_ids]" 
                            item_ids=$devision_data.users_ids
                            placement="right"
                            user_info=$users_info
                        }
                    </div>
                </div>

                <div class="control-group" id="devision_text">
                    <label class="control-label" for="elm_devision_description">{__("description")}:</label>
                    <div class="controls">
                        <textarea id="elm_devision_description" name="devision_data[description]" cols="35" rows="8" class="cm-wysiwyg input-large">{$devision_data.description}</textarea>
                    </div>
                </div>

                <div class="control-group {if $b_type == "G"}hidden{/if}" id="devision_text">
                    <label class="control-label" for="elm_devision_description">{__("description")}:</label>
                    <div class="controls">
                        <textarea id="elm_devision_description" name="devision_data[description]" cols="35" rows="8" class="cm-wysiwyg input-large">{$devision.description}</textarea>
                    </div>
                </div>


                <div class="control-group">
                    <label class="control-label" for="elm_devision_timestamp_{$id}">{__("creation_date")}</label>
                    <div class="controls">
                    {include file="common/calendar.tpl" date_id="elm_devision_timestamp_`$id`" date_name="devision_data[timestamp]" date_val=$devision.timestamp|default:$smarty.const.TIME start_year=$settings.Company.company_start_year}
                    </div>
                </div>


                {include file="common/select_status.tpl" input_name="devision_data[status]" id="elm_devision_status" obj_id=$id obj=$devision hidden=false}
              
            <!--content_general--></div>

            
        {capture name="buttons"}
            {if !$id}
                {include file="buttons/save_cancel.tpl" but_role="submit-link" but_target_form="devisions_form" but_name="dispatch[profiles.update_devision]"}
            {else}
                {include file="buttons/save_cancel.tpl" but_name="dispatch[profiles.update_devision]" but_role="submit-link" but_target_form="devisions_form" hide_first_button=$hide_first_button hide_second_button=$hide_second_button save=$id}
            {/if}
        {/capture}

    </form>

{/capture}


{include file="common/mainbox.tpl"
    title=($id) ? $devision_data.devision : __("Добавить новый отдел")
    content=$smarty.capture.mainbox
    buttons=$smarty.capture.buttons
    select_languages=true}

{** devision section **}
