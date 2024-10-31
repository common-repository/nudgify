<?php
/*
Plugin Name: Nudgify
Description: Install Nudgify on your WordPress website in less then 10 seconds. Integrate unique tracking code of Nudgify into every page of your website in one click.
Author: Nudgify
Version: 1.3.6
Author URI: https://nudgify.com
License: GPLv2
Plugin URI: https://nudgify.com/
*/

defined('ABSPATH') or die('Restricted access!');

define('NUDGIFY_PLUGIN_VERSION', '1.3.6');
define('NUDGIFY_PLUGIN_SLUG', 'nudgify');
define('NUDGIFY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NUDGIFY_PLUGIN_DIR', str_replace('\\', '/', dirname(__FILE__)));

require_once(NUDGIFY_PLUGIN_DIR . '/includes/settings.php');
require_once(NUDGIFY_PLUGIN_DIR . '/includes/functions.php');
require_once(NUDGIFY_PLUGIN_DIR . '/sentry/autoload.php');

if (!class_exists('Nudgify')) {
    class NudgifyOptions
    {
        const DO_MANUAL_SYNC = 'nudgify-domanualsync';
        const OPTIONS_GROUP = 'nudgify-options';

        const SAVED = 'nudgify-saved';
        const SITE_KEY = 'nudgify-site-key';
        const API_TOKEN = 'nudgify-api-token';
        const CONNECTED = 'nudgify-connected';
        const ENABLED = 'nudgify-enabled';
        const AUTOSYNC = 'nudgify-autosync';

        const options = [
            self::SAVED,
            self::ENABLED,
            self::SITE_KEY,
            self::API_TOKEN,
            self::CONNECTED,
            self::AUTOSYNC,
        ];
    }

    class Nudgify
    {
        private $product;
        private $sentry;

        public function __construct()
        {
            $this->sentry = new NudgifySentryClient(
                new NudgifySentryDirectEventCapture(new NudgifySentryDSN(NUDGIFY_SENTRY_DSN)),
                [
                    new NudgifySentryEnvironmentReporter(),
                    new NudgifySentryRequestReporter(),
                    new NudgifySentryExceptionReporter(),
                    new NudgifySentryClientSniffer(),
                    new NudgifySentryClientIPDetector(),
                ]
            );

            $this->init_base();
            $this->init_woocommerce_orders();
        }

        public function init_base()
        {
            add_action('wp_head', [$this, 'print_pixel']);

            add_action('admin_init', [$this, 'init_settings']);
            add_action('admin_menu', [$this, 'init_menu']);

            // add_action('admin_notices', [$this, 'configuration_notice']);

            add_action('add_option_' . NudgifyOptions::SITE_KEY, [$this, 'connect'], 999, 0);
            add_action('add_option_' . NudgifyOptions::API_TOKEN, [$this, 'connect'], 999, 0);
            add_action('update_option_' . NudgifyOptions::SITE_KEY, [$this, 'connect'], 999, 0);
            add_action('update_option_' . NudgifyOptions::API_TOKEN, [$this, 'connect'], 999, 0);
        }

        public function init_woocommerce_orders()
        {
            if (!nudgify_woocommerce_enabled()) {
                return;
            }

            add_action('admin_action_' . NudgifyOptions::DO_MANUAL_SYNC, [$this, 'sync_orders_manually']);
            add_action('woocommerce_new_order', [$this, 'post_woocommerce_order']);
            add_action('woocommerce_add_to_cart', [$this, 'post_woocommerce_add_to_cart'], 10, 6);
            add_action('woocommerce_remove_cart_item', [$this, 'post_woocommerce_remove_from_cart'], 10, 6);
            add_action('woocommerce_after_cart_item_quantity_update', [$this, 'post_woocommerce_update_cart_item_quantity'], 10, 6);
            
        
            add_action('woocommerce_checkout_order_processed', [$this, 'post_woocommerce_order']);
            add_action('woocommerce_order_status_cancelled', [$this, 'post_woocommerce_cancellation']);
            add_action('woocommerce_order_status_refunded', [$this, 'post_woocommerce_cancellation']);

            add_action('wp_ajax_nudgify_apply_discount', [$this, 'apply_discount'] );
            add_action('wp_ajax_nudgify_check_discount', [$this, 'check_discount_applied'] );
        }

        public function init_settings()
        {
            register_setting(NudgifyOptions::OPTIONS_GROUP, NudgifyOptions::SAVED, 'empty');
            register_setting(NudgifyOptions::OPTIONS_GROUP, NudgifyOptions::SITE_KEY, [$this, 'sanitise_nudgify_site_key']);
            register_setting(NudgifyOptions::OPTIONS_GROUP, NudgifyOptions::API_TOKEN, [$this, 'sanitise_nudgify_api_token']);
            register_setting(NudgifyOptions::OPTIONS_GROUP, NudgifyOptions::CONNECTED, 'intval');
            register_setting(NudgifyOptions::OPTIONS_GROUP, NudgifyOptions::ENABLED, 'intval');
            register_setting(NudgifyOptions::OPTIONS_GROUP, NudgifyOptions::AUTOSYNC, 'intval');
        }

        public function init_menu()
        {
            add_menu_page(
                'Nudgify',
                'Nudgify',
                'manage_options',
                NUDGIFY_PLUGIN_SLUG,
                [$this, 'options_form'],
                NUDGIFY_PLUGIN_URL . 'icon.png'
            );
        }

        public function configuration_notice()
        {
            if (get_option(NudgifyOptions::CONNECTED) && $_GET['page'] !== 'nudgify') {
                return;
            }

            echo implode("\n", [
                '<div class="notice notice-error is-dismissible">',
                '<p>You need to complete your Nudgify set-up <a href="admin.php?page=nudgify">Complete set-up</a></p>',
                '</div>'
            ]);
        }

        public function print_pixel()
        {
            $uuid = get_option(NudgifyOptions::SITE_KEY);
            $enabled = get_option(NudgifyOptions::ENABLED, true);

            if (!$this->is_valid_site_key($uuid) || !$enabled) {
                return;
            }

            $uuid = json_encode($uuid);
            $url = json_encode(NUDGIFY_PIXEL_BASE . '/pixel.js');

            $pixelData = [];

            if (nudgify_woocommerce_enabled()) {
                $pixelData['data'] = [
                    'cart' => [
                        'amount' => $this->get_cart_value(),
                        'currency' => get_woocommerce_currency(),
                    ]
                ];

                if (is_product()) {
                    $post = get_post();
                    $this->product = wc_get_product($post->ID);

                    $productStock = $this->get_product_stock();

                    if (is_null($productStock) && $this->product->is_type('variable')) {
                        $productStock = 0;
                        $variations = $this->product->get_available_variations();
                        foreach ($variations as $variation) {
                            $productStock += intval($variation['max_qty']);
                        }
                    }

                    $pixelData['data']['product'] = [
                        'id' => $this->product->get_id(),
                        'stock' => $productStock,
                        'image' => $this->get_product_image($this->product)
                    ];
                }
            }
            $pixelDataString = $this->pixel_data_string($pixelData);

            $variantWatcher = '';
            if (nudgify_woocommerce_enabled()) {
                $variantWatcher = implode("\n", array(
                    '    (function ($) { ',
                    '        if (! $) return;',
                    '        if (typeof $.noConflict === "function") $ = $.noConflict();',
                    '        if (! typeof $.prototype.on === "function") return;',
                    '        $(document).on("show_variation", function (event, variant) { ',
                    '            if (!variant.is_in_stock) return; ',
                    '            window.nudgify.product({ ',
                    '                id: variant.variation_id || null, ',
                    '                stock: variant.max_qty || null, ',
                    '                image: variant.image.thumb_src || null, ',
                    '            }) ',
                    '        }); ',
                    '    })(window.jQuery || null); ',
                ));
            }

            echo implode("\n", array(
                '<script>',
                "    {$pixelDataString} ",
                "    {$variantWatcher} ",
                '    (function(w){',
                '        var k="nudgify",n=w[k]||(w[k]={});',
                "        n.uuid={$uuid};",
                '        var d=document,s=d.createElement("script");',
                "        s.src={$url};",
                '        s.async=1;',
                '        s.charset="utf-8";',
                '        d.getElementsByTagName("head")[0].appendChild(s)',
                '    })(window)',
                '</script>',
            ));
        }

        public function post_woocommerce_order($orderId, $orderDetails = [])
        {
            $enabled = get_option(NudgifyOptions::ENABLED, true);
            $autosync = get_option(NudgifyOptions::AUTOSYNC, true);

            if (!(nudgify_woocommerce_enabled() && $enabled && $autosync)) {
                return;
            }

            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);

            $order = wc_get_order($orderId);
            $data = $this->prepare_order_data($order, $siteKey);

            $response = $this->post(NUDGIFY_ENDPOINT_WEBHOOK, $data, $apiToken);

            return $response['successful'];
        }

        public function post_woocommerce_add_to_cart( $cart_id, $product_id = null, $quantity=0) {
            $enabled = get_option(NudgifyOptions::ENABLED, true);
            $autosync = get_option(NudgifyOptions::AUTOSYNC, true);

            if (!(nudgify_woocommerce_enabled() && $enabled && $autosync)) {
                return;
            }

            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);

            if (is_null(WC()->session->get('nudgify_cart_id')) ) {
                WC()->session->set('nudgify_cart_id', uniqid());
            }

            $cart_id = WC()->session->get('nudgify_cart_id');

            $data = [
                'action' => 'add_to_cart',
                'site_key' => $siteKey,
                'cart_id' => $cart_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'product' => $this->prepare_product_data($product_id),
            ];
            
            $response = $this->post(NUDGIFY_ENDPOINT_WEBHOOK, $data, $apiToken);
            return $response['successful'];
        }

        public function post_woocommerce_update_cart_item_quantity($cart_item_key, $quantity = 0, $old_quantity = 0, $cart = null) {
            $enabled = get_option(NudgifyOptions::ENABLED, true);
            $autosync = get_option(NudgifyOptions::AUTOSYNC, true);

            if (!(nudgify_woocommerce_enabled() && $enabled && $autosync)) {
                return;
            }

            if ($quantity == $old_quantity ) {
                return;
            }

            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);

            if (is_null(WC()->session->get('nudgify_cart_id')) ) {
                WC()->session->set('nudgify_cart_id', uniqid());
            }

            $cart_id = WC()->session->get('nudgify_cart_id');
            
            $product_id = $cart->cart_contents[$cart_item_key]['product_id'];

            $data = [
                'action' => 'update_cart_item',
                'site_key' => $siteKey,
                'cart_id' => $cart_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'product' => $this->prepare_product_data($product_id),
            ];
            
            $response = $this->post(NUDGIFY_ENDPOINT_WEBHOOK, $data, $apiToken);
            return $response['successful'];
        }
        
        public function post_woocommerce_remove_from_cart($cart_item_key, $cart = null)
        {
            $enabled = get_option(NudgifyOptions::ENABLED, true);
            $autosync = get_option(NudgifyOptions::AUTOSYNC, true);

            if (!(nudgify_woocommerce_enabled() && $enabled && $autosync)) {
                return;
            }

            if (is_null(WC()->session->get('nudgify_cart_id')) ) {
                return;
            }
            
            $cart_id = WC()->session->get('nudgify_cart_id');
            

            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);

            $product_id = $cart->cart_contents[$cart_item_key]['product_id']; 

            $data = [
                'action' => 'remove_from_cart',
                'cart_item_key' => $cart_item_key,
                'site_key' => $siteKey,  
                'cart_id' => $cart_id,
                'product_id' => $product_id,
                'product' => $this->prepare_product_data($product_id),
            ];

            $response = $this->post(NUDGIFY_ENDPOINT_WEBHOOK, $data, $apiToken);

            return $response['successful'];            
        }

        public function post_woocommerce_cancellation($orderId)
        {
            $enabled = get_option(NudgifyOptions::ENABLED, true);
            $autosync = get_option(NudgifyOptions::AUTOSYNC, true);

            if (!(nudgify_woocommerce_enabled() && $enabled && $autosync)) {
                return;
            }

            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);

            $data = [
                'site_key' => $siteKey,
                'order_id' => $orderId,
                'action' => 'cancelled'
            ];;

            $response = $this->post(NUDGIFY_ENDPOINT_WEBHOOK, $data, $apiToken);

            return $response['successful'];
        }

        public function sync_orders_manually()
        {
            if(!current_user_can('manage_options')) {
                echo nudgify_build_feedback_message('manualsync', '419');
                
                exit();
            }

            $nonce = $_REQUEST['nudgify_manual_sync_nonce'] ?? '';
			
            if ( ! wp_verify_nonce( $nonce, 'nudgify_manual_sync_nonce' ) ) {
				echo nudgify_build_feedback_message('manualsync', '419');
                
                exit();
            }
			
            $enabled = get_option(NudgifyOptions::ENABLED, true);
            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);

            if (!(nudgify_woocommerce_enabled() && $enabled)) {
				echo nudgify_build_feedback_message('manualsync', '423');

                exit();
            }
			
            if (!($siteKey && $apiToken)) {
				echo nudgify_build_feedback_message('manualsync', '424');

				exit();
            }
			
            $data = [
                'site_key' => $siteKey,
                'orders' => []
            ];

            $acceptedStatuses = array_filter(array_keys(wc_get_order_statuses()), function ($status) {
                return !in_array($status, ['wc-refunded', 'wc-cancelled']);
            });

            $orders = wc_get_orders([
                'limit' => 30,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => $acceptedStatuses
            ]);
			
            foreach ($orders as $order) {
                // guard agains OrderRefund or OrderCancelled
                if (! method_exists($order, 'get_billing_last_name')) {
                    continue;
                }

                $orderData = $this->prepare_order_data($order, $siteKey);
                $orderData['ip'] = $order->get_customer_ip_address();

                $data['orders'][] = $orderData;
            }
			

            $response = $this->post(NUDGIFY_ENDPOINT_SYNC, $data, $apiToken);

            if ($response['successful']) {
                update_option(NudgifyOptions::CONNECTED, 1);
            }
			echo nudgify_build_feedback_message('manualsync', '200');

            exit();
        }

        public function apply_discount() {        
            $discountCode = $_POST['code'] ?? null;
            
            if (!WC()->cart->has_discount($discountCode)) {
                WC()->cart->apply_coupon($discountCode);
                echo json_encode(["status" => true]);
            } else {
                echo json_encode(["status" => false]);
            }

            wp_die();
        }  

        public function check_discount_applied() {
            $discountCode = $_POST['code'] ?? null;

            $response = ["status" => true];

            if (!WC()->cart->has_discount($discountCode)) {
                $response = ["status" => false];
            } 

            // check for valid discount code
            if (wc_get_coupon_id_by_code( $discountCode ) ) {
                $response['valid'] = true;
            } else {
                $response['valid'] = false;
            }


            echo json_encode($response);
            wp_die();
        }

        public function connect()
        {
            $siteKey = get_option(NudgifyOptions::SITE_KEY);
            $apiToken = get_option(NudgifyOptions::API_TOKEN);
            $enabled = get_option(NudgifyOptions::ENABLED, true);

            // it has been disabled.
            if (!$enabled) {
                return;
            }

            if (empty($siteKey) || empty($apiToken)) {
                update_option(NudgifyOptions::CONNECTED, 0);

                return;
            }

            $data = [
                'integration_identity' => NUDGIFY_INTEGRATION_NAME,
                'site_key' => $siteKey
            ];

            $response = $this->post(NUDGIFY_ENDPOINT_AUTH, $data, $apiToken);

            if ($response['successful']) {
                update_option(NudgifyOptions::CONNECTED, 1);
            } else {
                update_option(NudgifyOptions::CONNECTED, 0);
            }

            $this->add_feedback_message('connect', $response['code']);

            return true;
        }

        public function sanitise_nudgify_site_key($siteKey)
        {
            $siteKey = trim($siteKey);

            if (!$this->is_valid_site_key($siteKey) || empty($siteKey)) {
                add_settings_error(
                    NudgifyOptions::SITE_KEY,
                    esc_attr('settings_updated'),
                    'Please make sure you provide the correct site key',
                    'error'
                );
            }

            return $siteKey;
        }

        public function sanitise_nudgify_api_token($apiToken)
        {
            $apiToken = trim($apiToken);

            if (strlen($apiToken) !== 60 && !empty($apiToken)) {
                add_settings_error(
                    NudgifyOptions::API_TOKEN,
                    esc_attr('settings_updated'),
                    'Please make sure you provide the correct API key',
                    'error'
                );
            }

            return $apiToken;
        }

        public function options_form()
        {
            require_once(NUDGIFY_PLUGIN_DIR . '/includes/options.php');
        }

        public function add_feedback_message($group, $code)
        {
            $data = json_encode([
                'group' => $group,
                'code' => $code
            ]);

            set_transient("nudgify_feedback_message_{$group}", $data, 5 * MINUTE_IN_SECONDS);
        }

        private function log($message, $data)
        {
            $data['debug'] = [
                'nudgify_plugin_version' => NUDGIFY_PLUGIN_VERSION,
                'site_url' => get_site_url(),
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'woocommerce_version' => nudgify_woocommerce_version()
            ];

            $this->sentry->captureException(new Exception($message), null, $data);
        }

        private function pixel_data_string($data)
        {
            if (empty($data)) {
                return '';
            }

            $ini_precision = ini_get('precision');
            $ini_serialize_precision = ini_get('serialize_precision');

            if (version_compare(phpversion(), '7.1', '>=')) {
                ini_set('precision', 17);
                ini_set('serialize_precision', -1);
            }

            $pixelDataString = 'window.nudgify = window.nudgify || {};';
            $pixelDataString .= 'window.nudgify = Object.assign(window.nudgify, ' . json_encode($data) . ');';

            if (version_compare(phpversion(), '7.1', '>=')) {
                ini_set('precision', $ini_precision);
                ini_set('serialize_precision', $ini_serialize_precision);
            }

            return $pixelDataString;
        }

        private function prepare_order_data($order, $siteKey)
        {
            $lastname = $order->get_billing_last_name();
            if (strlen($lastname) > 0) {
                $lastname = $lastname[0];
            }

            return [
                'site_key' => $siteKey,
                'order_id' => $order->get_id(),
                'action' => 'created',
                'email' => $order->get_billing_email(),
                'name' => implode(' ', [$order->get_billing_first_name(), $lastname]),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'country' => $order->get_billing_country(),
                'ip' => $this->get_order_ip($order),
                'date' => $this->get_order_date($order),
                'line_items' => $this->get_order_items($order)
            ];
        }

        /**
         * Prepares the product data.
         *
         * @param int $product_id The ID of the product.
         * @return array The prepared product data.
         */
        private function prepare_product_data($product_id)
        {
            $product = wc_get_product( $product_id );
            if( $product ) {
                return [
                    'item_id' => $product->get_id(),
                    'item_variation_id' => $product->get_id(),
                    'item_name' => $product->get_title(),
                    'item_link' => get_permalink($product->get_id()),
                    'image_url' => $this->get_product_image($product)
                ];
            }

            return [];  
        }

        private function get_order_items($order)
        {
            $products = [];

            $items = $order->get_items();

            foreach ($items as $item) {
                $product = $item->get_product();

                $products[] = [
                    'item_id' => $item->get_product_id(),
                    'item_variation_id' => $product->get_id(),
                    'item_name' => $product->get_title(),
                    'item_link' => get_permalink($product->get_id()),
                    'image_url' => $this->get_product_image($product),
                ];
            }

            return $products;
        }

        private function get_order_date($order)
        {
            if (method_exists($order, 'get_date_created')) {
                $date = $order->get_date_created();
                if (!empty($date)) {
                    return gmdate('Y-m-d H:i:s', $date->getTimestamp());
                }
            }

            return gmdate('Y-m-d H:i:s', time());
        }

        private function get_order_ip($order)
        {
            $ips = [];
            $ipKeys = [
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'REMOTE_ADDR'
            ];

            foreach ($ipKeys as $key) {
                if (isset($_SERVER[$key])) {
                    $ips[] = $_SERVER[$key];
                }
            }

            $ips[] = $order->get_customer_ip_address();

            $ips = array_unique($ips);

            return reset($ips);
        }

        private function is_valid_site_key($siteKey)
        {
            $UUIDv4 = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/Di';
            return preg_match($UUIDv4, $siteKey);
        }

        private function get_product_stock()
        {
            if (!$this->product) {
                return null;
            }

            return $this->product->get_stock_quantity();
        }

        private function get_product_image($product)
        {
            if (!$product) {
                return null;
            }

            $image_id = $product->get_image_id();

            return wp_get_attachment_image_url($image_id, 'thumbnail');
        }

        private function get_cart_value()
        {
            $cart = WC()->cart;

            if (! $cart) {
                return 0;
            }

            return floatval($cart->get_cart_contents_total() + $cart->get_taxes_total());
        }

        private function post($url, $data, $apiToken)
        {
            $response = wp_remote_post($url, [
                'body' => wp_json_encode($data),
                'headers' => [
                    'Authorization' => "Bearer $apiToken",
                    'Content-Type' => 'application/json'
                ],
            ]);

            if (is_wp_error($response)) {
                $this->log("[NudgifyError] $url", [
                    'data' => $data,
                    'errors' => $response->get_error_messages()
                ]);
                $output = [
                    'code' => 500,
                    'successful' => false,
                    'message' => 'Error completing action',
                ];
            } else {
                $output = $response['response'];
                $output['successful'] = $output['code'] == 200;
            }

            return $output;
        }
    }

    $nudgify = new Nudgify();
}

register_uninstall_hook(__FILE__, 'nudgify_uninstall_hook');
register_activation_hook(__FILE__, 'nudgify_activation_hook');

function nudgify_uninstall_hook()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    foreach (NudgifyOptions::options as $option) {
        delete_option($option);
    }
}

function nudgify_activation_hook()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    update_option(NudgifyOptions::SITE_KEY, get_option(NudgifyOptions::SITE_KEY, ''));
    update_option(NudgifyOptions::API_TOKEN, get_option(NudgifyOptions::API_TOKEN, ''));
    update_option(NudgifyOptions::CONNECTED, get_option(NudgifyOptions::CONNECTED, 0));
    update_option(NudgifyOptions::ENABLED, get_option(NudgifyOptions::ENABLED, 1));
    update_option(NudgifyOptions::AUTOSYNC, get_option(NudgifyOptions::AUTOSYNC, 1));
    update_option(NudgifyOptions::SAVED, get_option(NudgifyOptions::SAVED, time()));
}
