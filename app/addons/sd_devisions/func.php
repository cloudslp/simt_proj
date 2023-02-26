<?php 
 use Tygh\Registry;
 use Tygh\Languages\Languages;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_devision_get_users($devision_id) {
    return !empty($devision_id) ? db_get_fields('SELECT user_id FROM ?:devision_users WHERE devision_id = ?i', $devision_id) : [];
}

function fn_get_devisions($params = [], $items_per_page = 0, $lang_code = CART_LANGUAGE)
{
    $default_params = array(
        'page' => 1,
        'items_per_page' => $items_per_page
    );

    $params = array_merge($default_params, $params);

    if (AREA == 'C') {
        $params['status'] = 'A';
    }

    $sortings = array(
        'timestamp' => '?:devisions.timestamp',
        'name' => '?:devision_descriptions.devision',
        'status' => '?:devisions.status',
    );

    $condition = $limit = $join = '';

    if (!empty($params['limit'])) {
        $limit = db_quote(' LIMIT 0, ?i', $params['limit']);
    }

    $sorting = db_sort($params, $sortings, 'name', 'asc');

    if (!empty($params['devision'])) {
        $condition .= db_quote(' AND ?:devision_descriptions.devision LIKE ?s', '%' . $params['devision'] . '%');
    }

    if (!empty($params['devision_id'])) {
        $condition .= db_quote(' AND ?:devisions.devision_id = ?i', $params['devision_id']);
    }

    if (!empty($params['item_ids'])) {
        $condition .= db_quote(' AND ?:devisions.devision_id IN (?n)', explode(',', $params['item_ids']));
    }

    if (!empty($params['status'])) {
        $condition .= db_quote(' AND ?:devisions.status = ?s', $params['status']);
    }

    $fields = array (
        '?:devisions.*',
        '?:devision_descriptions.devision',
        '?:devision_descriptions.description',
        );

    $join .= db_quote(' LEFT JOIN ?:devision_descriptions ON ?:devision_descriptions.devision_id = ?:devisions.devision_id AND ?:devision_descriptions.lang_code = ?s', $lang_code);

    if (!empty($params['items_per_page'])) {
        $params['total_items'] = db_get_field("SELECT COUNT(*) FROM ?:devisions $join WHERE 1 $condition");
        $limit = db_paginate($params['page'], $params['items_per_page'], $params['total_items']);
    }

    $devisions = db_get_hash_array(
        "SELECT ?p FROM ?:devisions " .
        $join .
        "WHERE 1 ?p ?p ?p",
        'devision_id', implode(', ', $fields), $condition, $sorting, $limit
    );

    $devision_image_ids = array_keys($devisions);
    $images = fn_get_image_pairs($devision_image_ids, 'devision', 'M', true, false, $lang_code);

    foreach ($devisions as $devision_id => $devision) {
        $devisions[$devision_id]['main_pair'] = !empty($images[$devision_id]) ? reset($images[$devision_id]) : array();
        $devisions[$devision_id]['manager'] = fn_get_user_short_info($devision['manager_id']);
    }

    return array($devisions, $params);
}

function fn_get_devision_data($devision_id = 0, $lang_code = CART_LANGUAGE) 
{
    $devision = [];

    if(!empty($devision_id)) {
        list($devisions) = fn_get_devisions([
            'devision_id' => $devision_id,
        ], 1, $lang_code);

        if (!empty($devisions)) {
            $devision = reset($devisions);
            $devision['users_ids'] = fn_devision_get_users($devision['devision_id']);
            $devision['manager'] = fn_get_user_short_info($devision['manager_id']);
        }
    }

    return $devision;
}

function fn_update_devision($data, $devision_id, $lang_code = DESCR_SL)
{
    if (isset($data['timestamp'])) {
        $data['timestamp'] = fn_parse_date($data['timestamp']);
    }
    if (isset($data['upd_timestamp'])) {
        $data['upd_timestamp'] = fn_parse_date($data['upd_timestamp']);
    }

    if (!empty($devision_id)) {
        db_query("UPDATE ?:devisions SET ?u WHERE devision_id = ?i", $data, $devision_id);
        db_query("UPDATE ?:devision_descriptions SET ?u WHERE devision_id = ?i AND lang_code = ?s", $data, $devision_id, $lang_code);
    } else {
        $devision_id = $data['devision_id'] = db_replace_into('devisions', $data);

        db_query("REPLACE INTO ?:devisions ?e", $data);

        foreach (Languages::getAll() as $data['lang_code'] => $v) {
            db_query("REPLACE INTO ?:devision_descriptions ?e", $data);
        }
    }

    if (!empty($devision_id)) {
        fn_attach_image_pairs('devision', 'devision', $devision_id, $lang_code);
    }

    $users_ids = !empty($_REQUEST['devision_data']['users_ids']) ? $_REQUEST['devision_data']['users_ids'] : [];

    fn_devision_delete_users($devision_id);
    fn_devision_add_users($devision_id, $users_ids);

    return $devision_id;
}

function fn_delete_devision ($devision_id) {
    if (!empty($devision_id)) {
        db_query("DELETE FROM ?:devisions WHERE devision_id = ?i", $devision_id);
        db_query("DELETE FROM ?:devision_descriptions WHERE devision_id = ?i", $devision_id);
    }
}
function fn_devision_delete_users ($devision_id) {
    db_query("DELETE FROM ?:devision_users WHERE devision_id = ?i", $devision_id);
}

function fn_devision_add_users($devision_id, $users_ids) {
    if (!empty($devision_id)) {

        $users_ids = explode(',', $users_ids);

        foreach ($users_ids as $user_id) {
            db_query("REPLACE INTO ?:devision_users ?e", [
                'devision_id' => $devision_id,
                'user_id' => $user_id,
            ]);
        }
    }
}


