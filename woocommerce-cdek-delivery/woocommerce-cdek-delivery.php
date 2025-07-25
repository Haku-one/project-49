<?php
/**
 * Plugin Name: WooCommerce CDEK Delivery
 * Plugin URI: https://github.com/your-username/woocommerce-cdek-delivery
 * Description: CDEK delivery integration for WooCommerce with interactive map, cost calculation and delivery points selection
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-cdek-delivery
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 4.0
 * WC tested up to: 8.4
 *
 * @package WooCommerce_CDEK_Delivery
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define plugin constants
define('WC_CDEK_VERSION', '1.0.0');
define('WC_CDEK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_CDEK_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main WooCommerce CDEK Delivery Class
 */
class WC_CDEK_Delivery {
    
    /**
     * Single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        add_action('wp_ajax_cdek_calculate_delivery', array($this, 'ajax_calculate_delivery'));
        add_action('wp_ajax_nopriv_cdek_calculate_delivery', array($this, 'ajax_calculate_delivery'));
        add_action('wp_ajax_cdek_get_offices', array($this, 'ajax_get_offices'));
        add_action('wp_ajax_nopriv_cdek_get_offices', array($this, 'ajax_get_offices'));
    }
    
    /**
     * Init WooCommerce CDEK Delivery when WordPress Initialises
     */
    public function init() {
        // Set up localisation
        $this->load_plugin_textdomain();
        
        // Include required files
        $this->includes();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('woocommerce-cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Include required core files
     */
    public function includes() {
        include_once WC_CDEK_PLUGIN_PATH . 'includes/class-wc-cdek-api.php';
        include_once WC_CDEK_PLUGIN_PATH . 'includes/class-wc-cdek-shipping-method.php';
        include_once WC_CDEK_PLUGIN_PATH . 'includes/class-wc-cdek-admin.php';
        include_once WC_CDEK_PLUGIN_PATH . 'includes/class-wc-cdek-frontend.php';
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_checkout() || is_cart()) {
            wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU', array(), null, true);
            wp_enqueue_script('wc-cdek-frontend', WC_CDEK_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'yandex-maps'), WC_CDEK_VERSION, true);
            wp_enqueue_style('wc-cdek-frontend', WC_CDEK_PLUGIN_URL . 'assets/css/frontend.css', array(), WC_CDEK_VERSION);
            
            wp_localize_script('wc-cdek-frontend', 'wc_cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_cdek_nonce'),
                'i18n' => array(
                    'select_office' => __('Select pickup point', 'woocommerce-cdek-delivery'),
                    'loading' => __('Loading...', 'woocommerce-cdek-delivery'),
                    'error' => __('Error loading data', 'woocommerce-cdek-delivery'),
                    'no_offices' => __('No pickup points found', 'woocommerce-cdek-delivery'),
                    'calculate_cost' => __('Calculate delivery cost', 'woocommerce-cdek-delivery')
                )
            ));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_script('wc-cdek-admin', WC_CDEK_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WC_CDEK_VERSION, true);
        wp_enqueue_style('wc-cdek-admin', WC_CDEK_PLUGIN_URL . 'assets/css/admin.css', array(), WC_CDEK_VERSION);
    }
    
    /**
     * Init shipping method
     */
    public function init_shipping_method() {
        if (!class_exists('WC_CDEK_Shipping_Method')) {
            include_once WC_CDEK_PLUGIN_PATH . 'includes/class-wc-cdek-shipping-method.php';
        }
    }
    
    /**
     * Add shipping method to WooCommerce
     */
    public function add_shipping_method($methods) {
        $methods['cdek'] = 'WC_CDEK_Shipping_Method';
        return $methods;
    }
    
    /**
     * AJAX handler for delivery cost calculation
     */
    public function ajax_calculate_delivery() {
        check_ajax_referer('wc_cdek_nonce', 'nonce');
        
        $from_city = sanitize_text_field($_POST['from_city'] ?? '');
        $to_city = sanitize_text_field($_POST['to_city'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);
        $dimensions = array(
            'length' => floatval($_POST['length'] ?? 0),
            'width' => floatval($_POST['width'] ?? 0),
            'height' => floatval($_POST['height'] ?? 0)
        );
        $delivery_type = sanitize_text_field($_POST['delivery_type'] ?? 'pickup');
        
        $api = new WC_CDEK_API();
        $result = $api->calculate_delivery_cost($from_city, $to_city, $weight, $dimensions, $delivery_type);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for getting CDEK offices
     */
    public function ajax_get_offices() {
        check_ajax_referer('wc_cdek_nonce', 'nonce');
        
        $city = sanitize_text_field($_POST['city'] ?? '');
        $api = new WC_CDEK_API();
        $offices = $api->get_offices($city);
        
        wp_send_json_success($offices);
    }
}

/**
 * Main instance of WC_CDEK_Delivery
 */
function WC_CDEK() {
    return WC_CDEK_Delivery::instance();
}

// Global for backwards compatibility
$GLOBALS['wc_cdek'] = WC_CDEK();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create database tables if needed
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cdek_offices';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        office_code varchar(20) NOT NULL,
        city varchar(100) NOT NULL,
        address text NOT NULL,
        phone varchar(50),
        work_time text,
        latitude decimal(10,8),
        longitude decimal(11,8),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY office_code (office_code)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});