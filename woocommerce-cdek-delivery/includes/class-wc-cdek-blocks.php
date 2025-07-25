<?php
/**
 * CDEK Blocks Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_CDEK_Blocks {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Регистрируем блок для карты CDEK
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_assets'));
        
        // Добавляем хуки для блочного checkout
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_pickup_point_from_api'), 10, 2);
        add_filter('woocommerce_store_api_checkout_order_received_rest_response', array($this, 'add_pickup_point_to_api_response'), 10, 3);
        
        // Регистрируем REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register blocks when WooCommerce Blocks is loaded
     */
    public function register_blocks() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
            add_action(
                'woocommerce_blocks_checkout_block_registration',
                array($this, 'register_checkout_block_integration')
            );
        }
    }
    
    /**
     * Register checkout block integration
     */
    public function register_checkout_block_integration($integration_registry) {
        $integration_registry->register(new WC_CDEK_Checkout_Block_Integration());
    }
    
    /**
     * Enqueue block assets
     */
    public function enqueue_block_assets() {
        if (is_checkout() && has_block('woocommerce/checkout')) {
            wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU', array(), null, true);
            wp_enqueue_script(
                'wc-cdek-blocks',
                WC_CDEK_PLUGIN_URL . 'assets/js/blocks.js',
                array('jquery', 'yandex-maps', 'wp-element', 'wp-i18n'),
                WC_CDEK_VERSION,
                true
            );
            wp_enqueue_style(
                'wc-cdek-blocks',
                WC_CDEK_PLUGIN_URL . 'assets/css/blocks.css',
                array(),
                WC_CDEK_VERSION
            );
            
            wp_localize_script('wc-cdek-blocks', 'wc_cdek_blocks', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('cdek/v1/'),
                'nonce' => wp_create_nonce('wc_cdek_nonce'),
                'i18n' => array(
                    'select_office' => __('Выберите пункт выдачи', 'woocommerce-cdek-delivery'),
                    'loading' => __('Загрузка...', 'woocommerce-cdek-delivery'),
                    'error' => __('Ошибка загрузки данных', 'woocommerce-cdek-delivery'),
                    'no_offices' => __('Пункты выдачи не найдены', 'woocommerce-cdek-delivery'),
                    'calculate_cost' => __('Рассчитать стоимость доставки', 'woocommerce-cdek-delivery'),
                    'enter_city' => __('Введите название города', 'woocommerce-cdek-delivery'),
                    'search' => __('Поиск', 'woocommerce-cdek-delivery'),
                    'available_points' => __('Доступные пункты выдачи:', 'woocommerce-cdek-delivery'),
                    'selected_point' => __('Выбранный пункт выдачи:', 'woocommerce-cdek-delivery'),
                    'address' => __('Адрес:', 'woocommerce-cdek-delivery'),
                    'phone' => __('Телефон:', 'woocommerce-cdek-delivery'),
                    'work_time' => __('Время работы:', 'woocommerce-cdek-delivery'),
                    'select_point_required' => __('Пожалуйста, выберите пункт выдачи CDEK', 'woocommerce-cdek-delivery')
                )
            ));
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('cdek/v1', '/offices', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_offices_rest'),
            'permission_callback' => '__return_true',
            'args' => array(
                'city' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        register_rest_route('cdek/v1', '/calculate', array(
            'methods' => 'POST',
            'callback' => array($this, 'calculate_delivery_rest'),
            'permission_callback' => '__return_true',
            'args' => array(
                'from_city' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'to_city' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'weight' => array(
                    'required' => true,
                    'type' => 'number'
                )
            )
        ));
    }
    
    /**
     * REST API handler for getting offices
     */
    public function get_offices_rest($request) {
        $city = $request->get_param('city');
        
        if (!class_exists('WC_CDEK_API')) {
            return new WP_Error('api_error', 'CDEK API class not found', array('status' => 500));
        }
        
        $api = new WC_CDEK_API();
        $offices = $api->get_delivery_points_with_map($city);
        
        return rest_ensure_response($offices);
    }
    
    /**
     * REST API handler for calculating delivery
     */
    public function calculate_delivery_rest($request) {
        $from_city = $request->get_param('from_city');
        $to_city = $request->get_param('to_city');
        $weight = $request->get_param('weight');
        
        if (!class_exists('WC_CDEK_API')) {
            return new WP_Error('api_error', 'CDEK API class not found', array('status' => 500));
        }
        
        $api = new WC_CDEK_API();
        $result = $api->calculate_delivery_cost($from_city, $to_city, $weight, array(), 'pickup');
        
        return rest_ensure_response($result);
    }
    
    /**
     * Save pickup point from Store API
     */
    public function save_pickup_point_from_api($order, $request) {
        $pickup_office = $request->get_param('cdek_pickup_office');
        
        if ($pickup_office && is_array($pickup_office)) {
            $order->update_meta_data('_cdek_pickup_office', $pickup_office);
            $order->update_meta_data('_cdek_pickup_office_code', $pickup_office['code'] ?? '');
            $order->update_meta_data('_cdek_pickup_office_address', $pickup_office['address'] ?? '');
        }
    }
    
    /**
     * Add pickup point to API response
     */
    public function add_pickup_point_to_api_response($response, $order, $request) {
        $pickup_office = $order->get_meta('_cdek_pickup_office');
        
        if ($pickup_office) {
            $response['cdek_pickup_office'] = $pickup_office;
        }
        
        return $response;
    }
}

/**
 * CDEK Checkout Block Integration
 */
class WC_CDEK_Checkout_Block_Integration implements Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
    
    /**
     * The name of the integration.
     */
    public function get_name() {
        return 'cdek-checkout-map';
    }
    
    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize() {
        $this->register_block_editor_script();
        $this->register_block_frontend_script();
    }
    
    /**
     * Returns an array of script handles to enqueue in the frontend context.
     */
    public function get_script_handles() {
        return array('wc-cdek-checkout-block-frontend');
    }
    
    /**
     * Returns an array of script handles to enqueue in the editor context.
     */
    public function get_editor_script_handles() {
        return array('wc-cdek-checkout-block-editor');
    }
    
    /**
     * An array of key, value pairs of data made available to the block on the client side.
     */
    public function get_script_data() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('cdek/v1/'),
            'nonce' => wp_create_nonce('wc_cdek_nonce'),
        );
    }
    
    /**
     * Register block editor script
     */
    private function register_block_editor_script() {
        wp_register_script(
            'wc-cdek-checkout-block-editor',
            WC_CDEK_PLUGIN_URL . 'assets/js/checkout-block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
            WC_CDEK_VERSION,
            true
        );
    }
    
    /**
     * Register block frontend script
     */
    private function register_block_frontend_script() {
        wp_register_script(
            'wc-cdek-checkout-block-frontend',
            WC_CDEK_PLUGIN_URL . 'assets/js/checkout-block-frontend.js',
            array('wp-element', 'wp-i18n', 'wc-blocks-checkout'),
            WC_CDEK_VERSION,
            true
        );
    }
}

new WC_CDEK_Blocks();