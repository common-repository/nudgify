<?php

defined('ABSPATH') or die('Restricted access!');

if (!function_exists('nudgify_woocommerce_enabled')) {
    function nudgify_woocommerce_enabled()
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
}

if (!function_exists('nudgify_woocommerce_version')) {
    function nudgify_woocommerce_version()
    {
        if (class_exists('WooCommerce')) {
            global $woocommerce;
            return $woocommerce->version;
        }
        return 'NA';
    }
}

if (!function_exists('nudgify_feedback_message')) {
    function nudgify_feedback_message($section)
    {
        $feedbackAvailable = get_transient("nudgify_feedback_message_{$section}");
        if (empty($_GET['page']) || $_GET['page'] !== 'nudgify' || empty($feedbackAvailable)) {
            return '';
        }
        
        $data = json_decode($feedbackAvailable, true);

        delete_transient("nudgify_feedback_message_{$section}");

        $group = empty($data['group']) ? null : $data['group'];
        $code = empty($data['code']) ? null : $data['code'];
		return nudgify_build_feedback_message($group, $code);
    }
}

if (!function_exists('nudgify_build_feedback_message')) {
    function nudgify_build_feedback_message($group, $code)
    {
        $messages = [
            'connect' => [
                '200' => [
                    'message' => 'Connection is working properly',
                    'type' => 'success',
                ],
                '422' => [
                    'message' => 'Please supply a valid Site Key and API Key',
                    'type' => 'error',
                ],
                '500' => [
                    'message' => 'There was an error submitting the orders',
                    'type' => 'error',
                ],
            ],
            'manualsync' => [
                '419' => [
                    'message' => 'Security verification failed',
                    'type' => 'error',
                ],
                '422' => [
                    'message' => 'Enabled Nudgify needs to sync orders',
                    'type' => 'error',
                ],
                '424' => [
                    'message' => 'Please supply a valid Site Key and API Key',
                    'type' => 'error',
                ],
                '500' => [
                    'message' => 'Error connecting woocommerce',
                    'type' => 'error',
                ],
                '200' => [
                    'message' => 'Orders synced successfully',
                    'type' => 'success',
                ]
            ],
        ];

        if (empty($messages[$group][$code])) {
            return '';
        }

        return '
            <div class="notice is-dismissible notice-' . $messages[$group][$code]['type'] . '">
                <p><b>' . $messages[$group][$code]['message'] . '</b></p>
            </div>
        ';
    }
}

if (!function_exists('nudgify_write_log')) {
    function nudgify_write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}