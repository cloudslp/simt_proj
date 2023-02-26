<?php
    use Tygh\Registry;
    use Tygh\Tygh;

    defined('BOOTSTRAP') or die('Access denied');

    if ($mode == 'devisions') {

        Tygh::$app['session']['continue_url'] = "profiles.devisions";
    
        $params = $_REQUEST;
    
        list($devisions, $search) = fn_get_devisions($params, Registry::get('settings.Appearance.products_per_page'));
    
        if (isset($search['page']) && ($search['page'] > 1) && empty($devisions)) {
            return [CONTROLLER_STATUS_NO_PAGE];
        }
    
        Tygh::$app['view']->assign('devisions', $devisions);
        Tygh::$app['view']->assign('search', $search);
        Tygh::$app['view']->assign('columns', 3);
        fn_add_breadcrumb(__('devisions'));

    } elseif ($mode == 'devision') {

        $devision_data = [];
        $devision_id = !empty($_REQUEST['devision_id']) ? $_REQUEST['devision_id'] : 0;
        $devision_data = fn_get_devision_data($devision_id, CART_LANGUAGE);

        if (empty($devision_data)) {
            return [CONTROLLER_STATUS_NO_PAGE];
        }

        $users = [];
        if (!empty($devision_data['users_ids'])) {
            foreach ($devision_data['users_ids'] as $user_id) {
                array_push($users, fn_get_user_short_info($user_id));
            }
        }

        Tygh::$app['view']->assign('devision_data', $devision_data);

        fn_add_breadcrumb(__('devisions'), 'profiles.devisions');
        fn_add_breadcrumb($devision_data['devision']);

        $params = $_REQUEST;
        $params['extend'] = ['description'];

        if (isset($search['page']) && ($search['page'] > 1) && empty($devision) && (!defined('AJAX_REQUEST'))) {
            return [CONTROLLER_STATUS_NO_PAGE];
        }

        Tygh::$app['view']->assign('users', $users);
    }