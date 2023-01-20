<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

use Tygh\Api;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\ObjectStatuses;
use Tygh\Enum\SiteArea;
use Tygh\Enum\UserTypes;
use Tygh\Enum\YesNo;
use Tygh\Registry;
use Tygh\Tools\Url;
use Tygh\Tygh;
use Tygh\Languages\Languages;

defined('BOOTSTRAP') or die('Access denied');

$auth = & Tygh::$app['session']['auth'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'm_delete') {
        if (!empty($_REQUEST['user_ids'])) {
            foreach ($_REQUEST['user_ids'] as $v) {
                fn_delete_user($v);
            }
        }

        return array(CONTROLLER_STATUS_OK, 'profiles.manage' . (isset($_REQUEST['user_type']) ? '?user_type=' . $_REQUEST['user_type'] : '' ));
    }

    if ($mode === 'export_range') {
        if (!empty($_REQUEST['user_ids'])) {

            if (empty(Tygh::$app['session']['export_ranges'])) {
                Tygh::$app['session']['export_ranges'] = array();
            }

            if (empty(Tygh::$app['session']['export_ranges']['users'])) {
                Tygh::$app['session']['export_ranges']['users'] = array('pattern_id' => 'users');
            }

            Tygh::$app['session']['export_ranges']['users']['data'] = array('user_id' => $_REQUEST['user_ids']);

            unset($_REQUEST['redirect_url']);

            return array(CONTROLLER_STATUS_REDIRECT, 'exim.export?section=users&pattern_id=' . Tygh::$app['session']['export_ranges']['users']['pattern_id']);
        }
    }

    $suffix = "";

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
    //
    // Create/Update user
    //
    if ($mode === 'update' || $mode === 'add') {
        $profile_id = !empty($_REQUEST['profile_id']) ? $_REQUEST['profile_id'] : 0;
        $_uid = !empty($profile_id) ? db_get_field("SELECT user_id FROM ?:user_profiles WHERE profile_id = ?i", $profile_id) : $auth['user_id'];
        if (empty($_REQUEST['user_id'])) {
            $user_id = ($mode === 'add') ? '' : $_uid;
        } else {
            $user_id = $_REQUEST['user_id'];
        }

        $mode = empty($_REQUEST['user_id']) ? 'add' : 'update';
        // TODO: FIXME user_type
        if (Registry::get('runtime.company_id') && $user_id != $auth['user_id']) {
            $_REQUEST['user_data']['user_type'] = !empty($_REQUEST['user_type']) ? $_REQUEST['user_type'] : 'C';
        }

        // Restricted admin cannot change its user type
        if (fn_is_restricted_admin($_REQUEST) && $user_id == $auth['user_id'] || ($user_id == $auth['user_id'] && $auth['area'] == 'A')) {
            $_REQUEST['user_type'] = $auth['user_type'];
            $_REQUEST['user_data']['user_type'] = $auth['user_type'];
        }

        /**
         * Only admin can set the api key.
         */
        if (empty($_REQUEST['user_api_status']) || !YesNo::toBool($_REQUEST['user_api_status'])) {
            $_REQUEST['user_data']['api_key'] = '';
        }

        if ($auth['user_type'] !== UserTypes::ADMIN) {
            unset($_REQUEST['user_data']['api_key']);

            if ($mode === 'add') {
                unset($_REQUEST['user_data']['raw_api_key']);
            } else {
                // Allow vendor admin to generate a new api key
                $old_api_status = (bool) db_get_field('SELECT api_key FROM ?:users WHERE user_id = ?i', $user_id);

                if (!$old_api_status) {
                    unset($_REQUEST['user_data']['raw_api_key']);
                }
            }
        }

        fn_restore_processed_user_password($_REQUEST['user_data'], $_POST['user_data']);

        $res = fn_update_user(
            $user_id,
            $_REQUEST['user_data'],
            $auth,
            !empty($_REQUEST['ship_to_another']),
            !empty($_REQUEST['notify_customer'])
        );

        if ($res) {
            list($user_id, $profile_id) = $res;

            if (!empty($_REQUEST['return_url'])) {
                return array(CONTROLLER_STATUS_OK, $_REQUEST['return_url']);
            }
        } else {
            fn_save_post_data('user_data');
            fn_delete_notification('changes_saved');
        }

        $redirect_params =  array(
            'user_id' => $user_id
        );

        if (Registry::get('settings.General.user_multiple_profiles') == 'Y') {
            $redirect_params['profile_id'] = $profile_id;
        }

        if (!empty($_REQUEST['user_type'])) {
            $redirect_params['user_type'] = $_REQUEST['user_type'];
        }

        if (!empty($_REQUEST['return_url'])) {
            $redirect_params['return_url'] = urlencode($_REQUEST['return_url']);
        }

        return array(CONTROLLER_STATUS_OK, 'profiles' . (!empty($user_id) ? '.update' : '.add') . '?' . http_build_query($redirect_params));
    }

    if ($mode == 'delete') {

        $user_type = fn_get_request_user_type($_REQUEST);
        fn_delete_user($_REQUEST['user_id']);

        return array(CONTROLLER_STATUS_REDIRECT, 'profiles.manage?user_type=' . $user_type);

    }

    if ($mode == 'delete_profile') {

        if (fn_is_restricted_admin($_REQUEST)) {
            return array(CONTROLLER_STATUS_DENIED);
        }

        $user_id = empty($_REQUEST['user_id']) ? $auth['user_id'] : $_REQUEST['user_id'];

        fn_delete_user_profile($user_id, $_REQUEST['profile_id']);

        return array(CONTROLLER_STATUS_OK, 'profiles.update?user_id=' . $user_id);

    }

    if ($mode === 'update_status') {
        if (isset($_REQUEST['id'])) {
            $notify = isset($_REQUEST['notify_user']) ? $_REQUEST['notify_user'] : YesNo::NO;
            $user_id = (int) $_REQUEST['id'];
            fn_change_user_status($user_id, $_REQUEST['status'], YesNo::toBool($notify));
        }
        return [CONTROLLER_STATUS_NO_CONTENT];
    }

    if ($mode == 'generate_api_key') {
        if (!defined('AJAX_REQUEST')) {
            return [CONTROLLER_STATUS_NO_PAGE];
        }

        Tygh::$app['ajax']->assign('new_api_key', Api::generateKey());
        exit;
    }

    if ($mode === 'm_activate' || $mode === 'm_disable') {
        if (!empty($_REQUEST['user_ids'])) {
            $user_ids = array_filter($_REQUEST['user_ids'], static function ($user_id) {
                return $user_id !== Tygh::$app['session']['auth']['user_id'];
            });
        }
        if (empty($user_ids)) {
            fn_set_notification(NotificationSeverity::ERROR, __('error'), __('error_status_not_changed'));
            return [CONTROLLER_STATUS_OK, 'profiles.manage' . (isset($_REQUEST['user_type']) ? '?user_type=' . $_REQUEST['user_type'] : '' )];
        }
        $new_status = ($mode === 'm_activate') ? ObjectStatuses::ACTIVE : ObjectStatuses::DISABLED;
        $notify = isset($_REQUEST['notify_user']) ? $_REQUEST['notify_user'] : YesNo::NO;
        foreach ($user_ids as $user_id) {
            fn_change_user_status((int) $user_id, $new_status, YesNo::toBool($notify));
        }
        return [CONTROLLER_STATUS_OK, 'profiles.manage' . (isset($_REQUEST['user_type']) ? '?user_type=' . $_REQUEST['user_type'] : '' )];
    }

    if ($mode === 'manage') {
        $params = [];
        if (!empty($_REQUEST['company_ids'])) {
            $params['company_ids'] = (array) $_REQUEST['company_ids'];
            $params['user_type'] = UserTypes::VENDOR;
        }

        if (!empty($params)) {
            unset($_REQUEST['redirect_url'], $_REQUEST['page']);

            return [CONTROLLER_STATUS_REDIRECT, Url::buildUrn(['profiles', 'manage'], $params)];
        }
    }

    if ($mode === 'anonymize') {
        $url = 'profiles.manage';

        if (empty($_REQUEST['user_id'])) {
            return [CONTROLLER_STATUS_REDIRECT, $url];
        }

        $user_id = (int) $_REQUEST['user_id'];
        $user_info = fn_get_user_info($user_id, false);

        if (isset($user_info['user_type']) && !UserTypes::isCustomer($user_info['user_type'])) {
            return [CONTROLLER_STATUS_REDIRECT, $url];
        }

        $url = 'profiles.update?user_id=' . $user_id;

        $user_data_collector = Tygh::$app['gdpr.user_data_collector'];
        $user_info = fn_get_user_info($user_id, false);
        $user_data = $user_data_collector->collect($user_info);

        $anonymizer = Tygh::$app['gdpr.anonymizer'];
        $user_data = $anonymized_user_data = $anonymizer->modify($user_data);

        $user_data_updater = Tygh::$app['gdpr.user_data_updater'];
        $user_data_updater->update($user_data);

        fn_set_notification(NotificationSeverity::NOTICE, __('notice'), __('user_anonymized'));
        fn_set_notification(NotificationSeverity::WARNING, __('warning'), __('check_other_sources_for_personal_data'));

        return [CONTROLLER_STATUS_REDIRECT, $url];
    }
    return array(CONTROLLER_STATUS_OK, 'profiles' . $suffix);
}

if ($mode === 'manage') {
    if (isset($_REQUEST['user_type'])) {
        $_REQUEST['user_type'] = strtoupper($_REQUEST['user_type']);
    }
    if (
        Registry::get('runtime.company_id')
        && !empty($_REQUEST['user_type'])
        && (
            $_REQUEST['user_type'] == 'P'
            || (
                $_REQUEST['user_type'] == 'A'
                && !fn_check_permission_manage_profiles('A')
            )
        )
    ) {
        return array(CONTROLLER_STATUS_DENIED);
    }

    if (!empty($_REQUEST['user_type']) && $_REQUEST['user_type'] == 'V' && fn_allowed_for('ULTIMATE')) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    list($users, $search) = fn_get_users($_REQUEST, $auth, Registry::get('settings.Appearance.admin_elements_per_page'));

    $user_ids = array_column($users, 'user_id');
    $orders_statistics = fn_get_user_order_statistics($user_ids);

    Tygh::$app['view']->assign('users', $users);
    Tygh::$app['view']->assign('search', $search);
    Tygh::$app['view']->assign('can_view_orders', fn_check_permissions('orders', 'manage', 'admin'));
    Tygh::$app['view']->assign('settled_statuses', fn_get_settled_order_statuses());
    Tygh::$app['view']->assign('orders_stats', $orders_statistics);

    if (!empty($search['user_type'])) {
        Tygh::$app['view']->assign('user_type_description', fn_get_user_type_description($search['user_type']));
    }

    $can_add_user = fn_check_view_permissions('profiles.add')
        && (isset($_REQUEST['user_type']))
        && fn_check_permission_manage_profiles($_REQUEST['user_type']);

    Tygh::$app['view']->assign('countries', fn_get_simple_countries(true, CART_LANGUAGE));
    Tygh::$app['view']->assign('states', fn_get_all_states());
    Tygh::$app['view']->assign('usergroups', fn_get_usergroups(array('status' => array('A', 'H')), DESCR_SL));
    Tygh::$app['view']->assign('can_add_user', $can_add_user);

} elseif ($mode == 'act_as_user' || $mode == 'view_product_as_user' || $mode == 'login_as_vendor') {

    $user_id = empty($_REQUEST['user_id']) ? 0 : (int) $_REQUEST['user_id'];

    if ($mode == 'login_as_vendor') {
        if (empty($_REQUEST['company_id'])) {
            return [CONTROLLER_STATUS_NO_PAGE];
        }

        $user_id = fn_get_company_root_admin_user_id((int) $_REQUEST['company_id']);
    }

    $condition = '';
    $_suffix = '';

    if (fn_allowed_for('MULTIVENDOR') && $mode == 'act_as_user') {
        $show_admin = isset($auth['company_id']) && $auth['company_id'] == 0;
        $condition = fn_get_company_condition('?:users.company_id', true, fn_get_styles_owner(), $show_admin);
    }

    $user_data = db_get_row("SELECT * FROM ?:users WHERE user_id = ?i $condition", $user_id);

    if (empty($user_data)) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }

    $act_as_self = $user_id == $auth['user_id'];
    if (!$act_as_self && !fn_check_permission_manage_profiles($user_data['user_type'])) {
        return [CONTROLLER_STATUS_DENIED];
    }

    if (!empty($user_data)) {
        if (!empty($_REQUEST['area'])) {
            $area = $_REQUEST['area'];
        } else {
            $area = fn_check_user_type_admin_area($user_data)
                ? 'A'
                : 'C';
        }

        if (fn_allowed_for('MULTIVENDOR')) {
            if ($user_data['user_type'] === 'V') {
                $area = $area === 'A'
                    ? 'V'
                    : $area;
            }
        }

        $sess_data = [
            'auth'        => fn_fill_auth($user_data, [], true, $area),
            'last_status' => empty(Tygh::$app['session']['last_status'])
                ? ''
                : Tygh::$app['session']['last_status'],
        ];

        $redirect_url = !empty($_REQUEST['redirect_url'])
            ? $_REQUEST['redirect_url']
            : '';

        $areas = [
            'A' => 'admin',
            'V' => 'vendor',
            'C' => 'customer',
        ];

        $old_sess_id = Tygh::$app['session']->getID();

        Registry::set('runtime.is_restoring_cart_from_backend', true);
        fn_clear_cart(Tygh::$app['session']['cart'], false, true);

        if (SiteArea::isStorefront($area)) {
            // Save unique key for session
            $session_key = fn_crc32(microtime()) . fn_crc32(microtime(true) + 1);

            $redirect_url = fn_link_attach($redirect_url, 'skey=' . $session_key);

            /** @var \Tygh\Storefront\Repository $storefront_repository */
            $storefront_repository = Tygh::$app['storefront.repository'];

            if (isset($_REQUEST['storefront_id'])) {
                $storefront_id = (int) $_REQUEST['storefront_id'];
            } else {
                $storefront_id = fn_get_storefront_id_from_uri($redirect_url);
            }

            /** @var \Tygh\Storefront\Storefront $storefront_of_redirect */
            $storefront_of_redirect = Tygh::$app['storefront'];
            if ($storefront_id) {
                $storefront_of_redirect = $storefront_repository->findById($storefront_id);
            }

            if (fn_allowed_for('ULTIMATE')) {
                // Redirect to the personal frontend
                $company_id = !empty($user_data['company_id'])
                    ? $user_data['company_id']
                    : fn_get_runtime_company_id();

                if (!$company_id) {
                    $company_id = fn_get_company_id_from_uri($redirect_url);
                }

                if ($company_id) {
                    $storefront_of_redirect = $storefront_repository->findByCompanyId($company_id);
                } else {
                    $storefront_of_redirect = $storefront_repository->findFirstActiveStorefront();
                }
                if (!$storefront_of_redirect) {
                    $storefront_of_redirect = $storefront_repository->findDefault();
                    if (!$storefront_of_redirect) {
                        fn_set_notification(NotificationSeverity::WARNING, __('notice'), __('no_active_storefronts'));
                        return [CONTROLLER_STATUS_REDIRECT, 'companies.manage'];
                    }
                }
            }

            $redirect_url = fn_link_attach($redirect_url, 'storefront_id=' . $storefront_of_redirect->storefront_id);

            $sess_data['store_access_key'] = $storefront_of_redirect->access_key;

            if ($act_as_self && !empty(Tygh::$app['session']['customization'])) {
                $sess_data['customization'] = Tygh::$app['session']['customization'];
                unset(Tygh::$app['session']['customization']);
            }

            fn_init_user_session_data($sess_data, $user_id, true);

            if (!empty(Tygh::$app['session']['notifications'])) {
                Tygh::$app['session']['notifications'] = [];
            }

            fn_set_storage_data('session_' . $session_key . '_data', serialize($sess_data));
        } else {
            fn_init_user_session_data($sess_data, $user_id, true);

            /** @var array $sess_data */

            // Set flag for backward compatibility
            $should_stop_session = version_compare(PHP_VERSION, '7.2.0', '>=');

            /** @var \Tygh\Web\Session $session */
            $session = Tygh::$app['session'];
            if ($should_stop_session) {
                // Stop session for rename it
                $session->shutdown();
            }
            $session->setName($areas[$area]);
            // Generating a new session ID for the user on whose behalf we want to login
            $sess_id = $session->regenerateID();
            if ($should_stop_session) {
                // Stop session because it started again in the regenerateID method
                $session->shutdown();
            }
            // Save new session data here, because shutdown may rewrite it
            $session->save($sess_id, $sess_data);

            if ($should_stop_session) {
                $session->start($sess_id);
            } else {
                // Restore old session name and ID to keep admin's login active
                $session->setName(ACCOUNT_TYPE);
                $session->setID($old_sess_id);
            }
        }
        $redirect_url = fn_url($redirect_url, $area);

        Registry::del('runtime.is_restoring_cart_from_backend');

        return [CONTROLLER_STATUS_REDIRECT, $redirect_url, $area !== AREA];
    }
} elseif ($mode == 'picker') {
    $params = $_REQUEST;
    $params['exclude_user_types'] = array ('A', 'V');
    $params['skip_view'] = 'Y';

    list($users, $search) = fn_get_users($params, $auth, Registry::get('settings.Appearance.admin_elements_per_page'));
    Tygh::$app['view']->assign('users', $users);
    Tygh::$app['view']->assign('search', $search);

    Tygh::$app['view']->assign('countries', fn_get_simple_countries(true, CART_LANGUAGE));
    Tygh::$app['view']->assign('states', fn_get_all_states());
    Tygh::$app['view']->assign('usergroups', fn_get_usergroups(array('status' => array('A', 'H')), CART_LANGUAGE));

    Tygh::$app['view']->display('pickers/users/picker_contents.tpl');
    exit;

} elseif ($mode == 'password_reminder') {

    $cron_password = Registry::get('settings.Security.cron_password');

    if ((!isset($_REQUEST['cron_password']) || $cron_password != $_REQUEST['cron_password']) && (!empty($cron_password))) {
        die(__('access_denied'));
    }

    $expire = Registry::get('settings.Security.account_password_expiration_period') * SECONDS_IN_DAY;

    if ($expire) {
        // Get available admins
        $recipients = db_get_array("SELECT user_id FROM ?:users WHERE user_type IN('A', 'V') AND status = 'A' AND (UNIX_TIMESTAMP() - password_change_timestamp) >= ?i", $expire);
        if (!empty($recipients)) {

            /** @var \Tygh\Notifications\EventDispatcher $event_dispatcher */
            $event_dispatcher = Tygh::$app['event.dispatcher'];

            foreach ($recipients as $v) {
                $_user_data = fn_get_user_info($v['user_id'], true);

                $event_dispatcher->dispatch('profile.password_reminder', [
                    'user_data' => $_user_data
                ]);
            }
        }

        fn_echo(__('administrators_notified', array(
            '[count]' => count($recipients)
        )));
    }

    exit;
} elseif ($mode == 'update' || $mode == 'add') {

    if (empty($_REQUEST['user_type']) && (empty($_REQUEST['user_id']) || $_REQUEST['user_id'] != $auth['user_id'])) {

        $user_type = fn_get_request_user_type($_REQUEST);

        $params = array();
        if (!empty($_REQUEST['user_id'])) {
            $params['user_id'] = $_REQUEST['user_id'];
        }
        $params['user_type'] = $user_type;

        return array(CONTROLLER_STATUS_REDIRECT, 'profiles.' . $mode . '?' . http_build_query($params));
    }
    $show_storefront_picker = false;

    if ($mode == 'add') {
        if (fn_allowed_for('ULTIMATE')) {
            if (!empty($_REQUEST['user_type']) && $_REQUEST['user_type'] == 'V') {
                return array(CONTROLLER_STATUS_NO_PAGE);
            }

            if (Registry::get('runtime.company_id')) {
                if (empty($_REQUEST['user_type'])) {
                    $_GET['user_type'] = 'C';

                    return array(CONTROLLER_STATUS_REDIRECT, 'profiles.add?' . http_build_query($_GET));
                } elseif ($_REQUEST['user_type'] == 'A' && !fn_check_permission_manage_profiles('A')) {
                    return array(CONTROLLER_STATUS_DENIED);
                }
            }
        }

        if (fn_allowed_for('MULTIVENDOR')) {
            $user_types = fn_get_user_types();

            if (Registry::get('runtime.company_id')) {
                if (empty($_REQUEST['user_type'])) {
                    return array(CONTROLLER_STATUS_REDIRECT, 'profiles.add?user_type=' . fn_get_request_user_type($_REQUEST));

                } elseif ($_REQUEST['user_type'] == 'C') {
                    return array(CONTROLLER_STATUS_DENIED);

                } elseif ($_REQUEST['user_type'] == 'A') {
                    $_GET['user_type'] = 'V';

                    return array(CONTROLLER_STATUS_REDIRECT, 'profiles.add?' . http_build_query($_GET));

                } elseif (empty($user_types[$_REQUEST['user_type']])) {
                    return array(CONTROLLER_STATUS_DENIED);
                }
            }
        }

        if (fn_allowed_for('MULTIVENDOR:ULTIMATE')) {
            $show_storefront_picker = UserTypes::isAdmin($_REQUEST['user_type']) && empty($auth['storefront_id']);
        }
    } else {
        if (fn_allowed_for('MULTIVENDOR')) {
            if (Registry::get('runtime.company_id') && !empty($_REQUEST['user_id']) && $_REQUEST['user_id'] != $auth['user_id']) {
                if (empty($_REQUEST['user_type'])) {
                    $_GET['user_type'] = fn_get_request_user_type($_REQUEST);

                    return array(CONTROLLER_STATUS_REDIRECT, 'profiles.update?' . http_build_query($_GET));
                } elseif ($_REQUEST['user_type'] == 'A') {
                    $_GET['user_type'] = 'V';

                    return array(CONTROLLER_STATUS_REDIRECT, 'profiles.update?' . http_build_query($_GET));
                }
            }
        }
    }

    if (
        Registry::get('runtime.company_id')
        && !empty($_REQUEST['user_type'])
        && (
            $_REQUEST['user_type'] == 'P'
            || (
                $_REQUEST['user_type'] == 'A'
                && !fn_check_permission_manage_profiles('A')
            )
        )
    ) {
        return array(CONTROLLER_STATUS_DENIED);
    }

    if (!empty($_REQUEST['user_id']) && !empty($_REQUEST['user_type'])) {
        if ($_REQUEST['user_id'] == $auth['user_id'] && defined('RESTRICTED_ADMIN') && !in_array($_REQUEST['user_type'], array('A', ''))) {
            return array(CONTROLLER_STATUS_REDIRECT, 'profiles.update?user_id=' . $_REQUEST['user_id']);
        }
    }

    // copy to add below this line
    $profile_id = !empty($_REQUEST['profile_id']) ? $_REQUEST['profile_id'] : 0;
    $_uid = !empty($profile_id) ? db_get_field("SELECT user_id FROM ?:user_profiles WHERE profile_id = ?i", $profile_id) : $auth['user_id'];
    $user_id = empty($_REQUEST['user_id']) ? (($mode == 'add') ? '' : $_uid) : $_REQUEST['user_id'];

    $orders_statistics = [];
    if (!empty($_REQUEST['profile']) && $_REQUEST['profile'] == 'new') {
        $user_data = fn_get_user_info((int) $user_id, false);
    } else {
        $user_data = fn_get_user_info((int) $user_id, true, $profile_id);

        $orders_statistics = fn_get_user_order_statistics([$user_id]);
    }

    $saved_user_data = fn_restore_post_data('user_data');
    if (!empty($saved_user_data)) {
        $user_data = fn_array_merge($user_data, $saved_user_data);
    }

    if ($mode == 'update') {
        if (
            empty($user_data)
            || (
                isset($user_data['storefront_id'])
                && !fn_check_permission_storefronts($user_data['storefront_id'])
            )
        ) {
            return array(CONTROLLER_STATUS_NO_PAGE);
        }

        if (
            fn_allowed_for('MULTIVENDOR:ULTIMATE')
            && UserTypes::isAdmin($user_data['user_type'])
            && empty($auth['storefront_id'])
            && !YesNo::toBool($user_data['is_root'])
        ) {
            $show_storefront_picker = true;
        }
    }

    $user_data['user_id'] = empty($user_data['user_id']) ? (!empty($user_id) ? $user_id : 0) : $user_data['user_id'];
    $user_data['user_type'] = empty($user_data['user_type']) ? 'C' : $user_data['user_type'];
    $user_type = (!empty($_REQUEST['user_type'])) ? ($_REQUEST['user_type']) : $user_data['user_type'];
    $auth['is_root'] = isset($auth['is_root']) ? $auth['is_root'] : '';

    $usergroups = fn_get_available_usergroups($user_type);

    $navigation = [
        'general' => [
            'title' => __('general'),
            'js' => true
        ],
        'addons' => [
            'title' => __('addons'),
            'js' => true
        ]
    ];

    if ($mode == 'update'
        && !Registry::get('runtime.company_id')
        && ($user_type === UserTypes::CUSTOMER
        || ($user_type === UserTypes::VENDOR && fn_check_permission_manage_profiles($user_type))
        || ($user_type === UserTypes::ADMIN && fn_check_current_user_access('manage_admin_usergroups') && !empty($user_data['is_root']) && $user_data['is_root'] !== YesNo::YES))
    ) {
        $navigation['usergroups'] = [
            'title' => __('usergroups'),
            'js'    => true,
        ];

    } else {
        $usergroups = [];
    }

    if (empty($user_data['api_key'])) {
        Tygh::$app['view']->assign('new_api_key', Api::generateKey());
    }

    /**
     * Only admin can set the api key.
     */
    if (
        fn_check_user_type_admin_area_for_api($user_data)
        && !empty($user_data['user_id'])
        && (
            $auth['user_type'] === UserTypes::ADMIN
            || $user_data['api_key']
        )
    ) {
        $navigation['api'] = [
            'title' => __('api_access'),
            'js'    => true
        ];

        Tygh::$app['view']->assign('show_api_tab', true);

        if ($auth['user_type'] !== UserTypes::ADMIN) {
            Tygh::$app['view']->assign('hide_api_checkbox', true);
        }
    }

    Registry::set('navigation.tabs', $navigation);

    Tygh::$app['view']->assign('usergroups', $usergroups);
    Tygh::$app['view']->assign('hide_inputs', !fn_check_editable_permissions($auth, $user_data));

    $profile_fields = fn_get_profile_fields($user_type);
    Tygh::$app['view']->assign('user_type', $user_type);
    Tygh::$app['view']->assign('can_view_orders', fn_check_permissions('orders', 'manage', 'admin'));
    Tygh::$app['view']->assign('settled_statuses', fn_get_settled_order_statuses());
    Tygh::$app['view']->assign('profile_fields', $profile_fields);
    Tygh::$app['view']->assign('user_data', $user_data);
    Tygh::$app['view']->assign('orders_stats', $orders_statistics);
    Tygh::$app['view']->assign('ship_to_another', fn_check_shipping_billing($user_data, $profile_fields));
    if (Registry::get('settings.General.user_multiple_profiles') == 'Y' && !empty($user_id)) {
        Tygh::$app['view']->assign('user_profiles', fn_get_user_profiles($user_id));
    }

    Tygh::$app['view']->assign('countries', fn_get_simple_countries(true, CART_LANGUAGE));
    Tygh::$app['view']->assign('states', fn_get_all_states());
    Tygh::$app['view']->assign('show_storefront_picker', $show_storefront_picker);

    if (
        !empty($user_data['user_id'])
        && !empty($user_data['user_type'])
        && UserTypes::isAdmin($user_data['user_type'])
    ) {
        Tygh::$app['view']->assign([
            'helpdesk_connect_url'    => Tygh::$app['helpdesk.connect_url'],
            'helpdesk_disconnect_url' => Tygh::$app['helpdesk.disconnect_url'],
        ]);
    }
}

if ($mode == 'get_customer_list') {
    $page_number = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
    $page_size = isset($_REQUEST['page_size']) ? (int) $_REQUEST['page_size'] : 10;
    $search_query = isset($_REQUEST['q']) ? $_REQUEST['q'] : null;

    $params = array(
        'area' => 'A',
        'page' => $page_number,
        'extended_search' => false,
        'search_query' => $search_query,
        'items_per_page' => $page_size,
        'exclude_user_types' => array ('A', 'V')
    );

    list($users, $params) = fn_get_users($params, $auth, $page_size);

    $objects = array_values(array_map(function ($customer_list) {
        $customer_name = trim($customer_list['firstname'] . ' ' . $customer_list['lastname']);
        return array(
            'id' => $customer_list['user_id'],
            'text' => $customer_name ? $customer_name : $customer_list['email'],
            'email' => $customer_list['email'],
            'phone' => $customer_list['phone'],
        );
    }, $users));

    Tygh::$app['ajax']->assign('objects', $objects);
    Tygh::$app['ajax']->assign('total_objects', isset($params['total_items']) ? $params['total_items'] : count($objects));

    exit;
}

if ($mode === 'get_manager_list') {
    $params = [
        'area'         => SiteArea::ADMIN_PANEL,
        'search_query' => isset($_REQUEST['q']) ? $_REQUEST['q'] : null,
    ];

    if (fn_allowed_for('ULTIMATE')) {
        $params['user_type'] = UserTypes::ADMIN;
    } else {
        $params['user_type'] = UserTypes::VENDOR;
        $params['company_id'] = isset($_REQUEST['company_id']) ? $_REQUEST['company_id'] : null;
    }

    list($objects, $params) = fn_get_users($params, $auth);
    $list_of_finded_managers = array_column($objects, 'user_id');
    if (!in_array($auth['user_id'], $list_of_finded_managers)) {
        $current_user = fn_get_user_short_info($auth['user_id']);
        $objects[] = $current_user;
    }
    if (
        isset($_REQUEST['ids'])
        && is_array($_REQUEST['ids'])
    ) {
        $current_issuer_id = reset($_REQUEST['ids']);

        if (
            $current_issuer_id
            && $current_issuer_id !== $auth['user_id']
            && !in_array($current_issuer_id, $list_of_finded_managers)
        ) {
            $current_issuer = fn_get_user_short_info($current_issuer_id);
            $objects[] = $current_issuer;
        }
    }
    $objects = array_map(static function ($profile) {
        $name = empty($profile['firstname']) && empty($profile['lastname'])
            ? $profile['email']
            : $profile['firstname'] . ' ' . $profile['lastname'];
        return [
            'id'   => $profile['user_id'],
            'text' => $name,
        ];
    }, $objects);

    Tygh::$app['ajax']->assign('objects', $objects);
    Tygh::$app['ajax']->assign('total_objects', isset($params['total_items']) ? $params['total_items'] : count($objects));

    return[CONTROLLER_STATUS_NO_CONTENT];

}  elseif ($mode == 'add_devision' || $mode == 'update_devision') {
    // fn_print_die('end');
    $devision_id = !empty($_REQUEST['devision_id']) ? $_REQUEST['devision_id'] : 0;
    $devision_data = fn_get_devision_data($devision_id, DESCR_SL);
    
    if (empty($devision_data) && $mode == 'update') {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    
    Tygh::$app['view']->assign([
        'devision_data' => $devision_data,
        'manager_info' => !empty($devision_data['manager_id']) ? fn_get_user_short_info($devision_data['manager_id']) : [],
    ]);
    //fn_print_die($devision_data);
}   elseif ($mode== 'manage_devisions') {
    //fn_print_die('end');
    list($devisions, $search) = fn_get_devisions($_REQUEST, Registry::get('settings.Appearance.admin_elements_per_page'), DESCR_SL);
    
    Tygh::$app['view']->assign('devisions', $devisions);
    Tygh::$app['view']->assign('search', $search);
}

function fn_get_devision_data ($devision_id = 0, $lang_code = CART_LANGUAGE) 
{
    $devision = [];

    if(!empty($devision_id)) {
        list($devisions) = fn_get_devisions([
            'devision_id' => $devision_id,
        ], 1, $lang_code);

        if (!empty($devisions)) {
            $devision = reset($devisions);
            $devision['users_ids'] = fn_devision_get_users($devision['devision_id']);
            $devision['manager_info'] = fn_get_user_short_info($devision['manager_id']);
        }
    }

    return $devision;
}

function fn_get_devisions($params = [], $items_per_page = 0, $lang_code = CART_LANGUAGE)
{
    // Set default values to input params
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
        $devisions[$devision_id]['manager_info'] = fn_get_user_short_info($devision['manager_id']);
    }

    return array($devisions, $params);
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

function fn_devision_get_users($devision_id) {
    return !empty($devision_id) ? db_get_fields('SELECT user_id FROM ?:devision_users WHERE devision_id = ?i', $devision_id) : [];
}