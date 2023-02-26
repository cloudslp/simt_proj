<?php
    use Tygh\Registry;
    use Tygh\Tygh;
    defined('BOOTSTRAP') or die('Access denied');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $suffix = '';
        fn_trusted_vars('devision_data');

        if ($mode == 'update_devision') {
            $devision_id = !empty($_REQUEST['devision_id']) ? $_REQUEST['devision_id'] : 0;
            $data = !empty($_REQUEST['devision_data']) ? $_REQUEST['devision_data'] : [];
    
            if ($devision_id) {
                $suffix = ".update_devision?devision_id={$devision_id}";
            } else {
                $suffix = ".manage_devisions";
            }
    
            $devision_id = fn_update_devision($data, $devision_id);
    
        } elseif ($mode == 'delete_devision') {
    
            $devision_id = !empty($_REQUEST['devision_id']) ? $_REQUEST['devision_id'] : 0;
            fn_delete_devision($devision_id);
    
            $suffix = '.manage_devisions';
        }
        return [CONTROLLER_STATUS_OK, 'profiles' . $suffix];
    }
        if ($mode === 'add_devision' || $mode === 'update_devision') {
            $devision_id = !empty($_REQUEST['devision_id']) ? (int) $_REQUEST['devision_id'] : 0;
            $devision_data = fn_get_devision_data($devision_id, DESCR_SL);
        
            if (empty($devision_data) && $mode === 'update') {
                return [CONTROLLER_STATUS_NO_PAGE];
            }
        
            Tygh::$app['view']->assign([
                'devision_data' => $devision_data,
                'manager_info' => !empty($devision_data['manager_id']) ? fn_get_user_short_info($devision_data['manager_id']) : [],
            ]);
        } elseif ($mode === 'manage_devisions') {
            list($devisions, $search) = fn_get_devisions($_REQUEST, Registry::get('settings.Appearance.admin_elements_per_page'), DESCR_SL);
            Tygh::$app['view']->assign('devisions', $devisions);
            Tygh::$app['view']->assign('search', $search);
        }
    