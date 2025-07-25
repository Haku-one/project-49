<?php
/**
 * Plugin Name: CDEK Доставка
 * Plugin URI: https://github.com/your-username/cdek-delivery
 * Description: Плагин интеграции службы доставки CDEK для WooCommerce с выбором пунктов выдачи на карте
 * Version: 1.0.0
 * Author: Ваше Имя
 * License: GPL v2 or later
 * Text Domain: cdek-delivery
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CDEK_DELIVERY_VERSION', '1.0.0');
define('CDEK_DELIVERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDEK_DELIVERY_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Check if WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>CDEK Доставка требует установки и активации WooCommerce!</p></div>';
    });
    return;
}

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', 'cdek_delivery_init');
function cdek_delivery_init() {
    // Load text domain
    load_plugin_textdomain('cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Include required files
    require_once CDEK_DELIVERY_PLUGIN_PATH . 'includes/class-cdek-shipping-method.php';
    require_once CDEK_DELIVERY_PLUGIN_PATH . 'includes/class-cdek-api.php';
    
    // Add shipping method to WooCommerce
    add_filter('woocommerce_shipping_methods', 'add_cdek_shipping_method');
}

/**
 * Add CDEK shipping method to WooCommerce
 */
function add_cdek_shipping_method($methods) {
    $methods['cdek_delivery'] = 'WC_CDEK_Shipping_Method';
    return $methods;
}

/**
 * Enqueue scripts and styles
 */
add_action('wp_enqueue_scripts', 'cdek_delivery_enqueue_scripts');
function cdek_delivery_enqueue_scripts() {
    if (is_checkout()) {
        // Yandex Maps API
        wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU', array(), null, true);
        
        // CDEK scripts
        wp_enqueue_script('cdek-delivery-checkout', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/checkout.js', array('jquery', 'yandex-maps'), CDEK_DELIVERY_VERSION, true);
        wp_enqueue_style('cdek-delivery-checkout', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/checkout.css', array(), CDEK_DELIVERY_VERSION);
        
        // Localize script
        wp_localize_script('cdek-delivery-checkout', 'cdek_delivery_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cdek_delivery_nonce'),
            'i18n' => array(
                'select_office' => __('Выберите пункт выдачи', 'cdek-delivery'),
                'loading' => __('Загрузка...', 'cdek-delivery'),
                'error' => __('Ошибка загрузки данных', 'cdek-delivery'),
                'no_offices' => __('Пункты выдачи не найдены', 'cdek-delivery'),
                'enter_city' => __('Введите название города', 'cdek-delivery'),
                'search' => __('Поиск', 'cdek-delivery'),
                'available_points' => __('Доступные пункты выдачи:', 'cdek-delivery'),
                'selected_point' => __('Выбранный пункт выдачи:', 'cdek-delivery'),
                'address' => __('Адрес:', 'cdek-delivery'),
                'phone' => __('Телефон:', 'cdek-delivery'),
                'work_time' => __('Время работы:', 'cdek-delivery'),
                'select_point_required' => __('Пожалуйста, выберите пункт выдачи CDEK', 'cdek-delivery')
            )
        ));
    }
}

/**
 * AJAX handlers
 */
add_action('wp_ajax_cdek_get_offices', 'cdek_ajax_get_offices');
add_action('wp_ajax_nopriv_cdek_get_offices', 'cdek_ajax_get_offices');
function cdek_ajax_get_offices() {
    // Security check
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cdek_delivery_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $city = sanitize_text_field($_POST['city'] ?? '');
    
    if (empty($city)) {
        wp_send_json_error(array('message' => 'City parameter is required'));
        return;
    }
    
    // Get offices from API
    $api = new CDEK_API();
    $offices = $api->get_delivery_points($city);
    
    if (!empty($offices)) {
        wp_send_json_success($offices);
    } else {
        wp_send_json_error(array('message' => 'No offices found for city: ' . $city));
    }
}

add_action('wp_ajax_cdek_calculate_delivery', 'cdek_ajax_calculate_delivery');
add_action('wp_ajax_nopriv_cdek_calculate_delivery', 'cdek_ajax_calculate_delivery');
function cdek_ajax_calculate_delivery() {
    // Security check
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cdek_delivery_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $from_city = sanitize_text_field($_POST['from_city'] ?? '');
    $to_city = sanitize_text_field($_POST['to_city'] ?? '');
    $weight = floatval($_POST['weight'] ?? 0);
    $office_code = sanitize_text_field($_POST['office_code'] ?? '');
    
    if (empty($from_city) || empty($to_city) || $weight <= 0) {
        wp_send_json_error(array('message' => 'Invalid parameters'));
        return;
    }
    
    // Calculate delivery cost
    $api = new CDEK_API();
    $cost = $api->calculate_delivery_cost($from_city, $to_city, $weight, $office_code);
    
    if ($cost !== false) {
        wp_send_json_success(array('cost' => $cost));
    } else {
        wp_send_json_error(array('message' => 'Failed to calculate delivery cost'));
    }
}

/**
 * Add pickup point selection to checkout
 */
add_action('wp_footer', 'cdek_add_pickup_point_selector');
function cdek_add_pickup_point_selector() {
    if (!is_checkout()) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Add CDEK pickup point selector
        function addCdekPickupSelector() {
            if ($('.cdek-pickup-selector').length === 0) {
                var shippingMethods = $('.wc-block-checkout__shipping-option, .woocommerce-shipping-methods');
                if (shippingMethods.length > 0) {
                    var selectorHtml = `
                        <div class="cdek-pickup-selector" style="margin: 20px 0; display: none;">
                            <h4>Выберите пункт выдачи CDEK</h4>
                            
                            <div class="cdek-search-container">
                                <input type="text" id="cdek-city-search" placeholder="Введите название города" />
                                <button type="button" id="cdek-search-btn">Поиск</button>
                            </div>
                            
                            <div id="cdek-map-container" style="display: none;">
                                <div id="cdek-map" style="width: 100%; height: 400px; margin: 15px 0;"></div>
                            </div>
                            
                            <div id="cdek-offices-list" style="display: none;">
                                <h5>Доступные пункты выдачи:</h5>
                                <div class="cdek-offices-container"></div>
                            </div>
                            
                            <input type="hidden" id="cdek-selected-office" name="cdek_pickup_office" value="" />
                            <div id="cdek-selected-office-info" style="display: none;">
                                <h5>Выбранный пункт выдачи:</h5>
                                <div class="cdek-office-details"></div>
                            </div>
                        </div>
                    `;
                    
                    shippingMethods.after(selectorHtml);
                    console.log('CDEK pickup selector added');
                    
                    // Initialize functionality
                    if (typeof window.initCdekDelivery === 'function') {
                        window.initCdekDelivery();
                    }
                }
            }
        }
        
        // Try to add selector multiple times
        setTimeout(addCdekPickupSelector, 1000);
        setTimeout(addCdekPickupSelector, 3000);
        setTimeout(addCdekPickupSelector, 5000);
        
        // Observe DOM changes
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    setTimeout(addCdekPickupSelector, 500);
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
}

/**
 * Save pickup point data to order
 */
add_action('woocommerce_checkout_update_order_meta', 'cdek_save_pickup_point_to_order');
function cdek_save_pickup_point_to_order($order_id) {
    if (isset($_POST['cdek_pickup_office']) && !empty($_POST['cdek_pickup_office'])) {
        $office_data = json_decode(stripslashes($_POST['cdek_pickup_office']), true);
        
        if ($office_data) {
            update_post_meta($order_id, '_cdek_pickup_office', $office_data);
            update_post_meta($order_id, '_cdek_pickup_office_code', $office_data['code']);
            update_post_meta($order_id, '_cdek_pickup_office_address', $office_data['address']);
        }
    }
}

/**
 * Display pickup point info in admin order page
 */
add_action('woocommerce_admin_order_data_after_shipping_address', 'cdek_display_pickup_point_in_admin');
function cdek_display_pickup_point_in_admin($order) {
    $pickup_office = get_post_meta($order->get_id(), '_cdek_pickup_office', true);
    
    if ($pickup_office) {
        ?>
        <div class="cdek-pickup-info" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
            <h4>Пункт выдачи CDEK</h4>
            <p>
                <strong>Код:</strong> <?php echo esc_html($pickup_office['code']); ?><br>
                <strong>Адрес:</strong> <?php echo esc_html($pickup_office['address']); ?><br>
                <?php if (!empty($pickup_office['phone'])): ?>
                    <strong>Телефон:</strong> <?php echo esc_html($pickup_office['phone']); ?><br>
                <?php endif; ?>
                <?php if (!empty($pickup_office['work_time'])): ?>
                    <strong>Время работы:</strong> <?php echo esc_html($pickup_office['work_time']); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Create plugin tables on activation
 */
register_activation_hook(__FILE__, 'cdek_delivery_create_tables');
function cdek_delivery_create_tables() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cdek_offices';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        code varchar(50) NOT NULL,
        name varchar(255) NOT NULL,
        city varchar(100) NOT NULL,
        address text NOT NULL,
        phone varchar(50),
        work_time text,
        latitude decimal(10, 8),
        longitude decimal(11, 8),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        KEY city (city)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
?>