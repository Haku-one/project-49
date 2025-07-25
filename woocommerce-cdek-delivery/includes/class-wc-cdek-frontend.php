<?php
/**
 * CDEK Frontend Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_CDEK_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        add_action('woocommerce_review_order_after_shipping', array($this, 'display_pickup_selector'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_pickup_point'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_pickup_point_in_admin'));
        add_action('woocommerce_email_after_order_table', array($this, 'display_pickup_point_in_email'), 20, 4);
        add_filter('woocommerce_order_shipping_to_display', array($this, 'add_pickup_point_to_shipping_display'), 10, 2);
    }
    
    /**
     * Display pickup point selector on checkout
     */
    public function display_pickup_selector() {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $show_pickup = false;
        
        if (!empty($chosen_methods)) {
            foreach ($chosen_methods as $method) {
                if (strpos($method, 'cdek_pickup') !== false) {
                    $show_pickup = true;
                    break;
                }
            }
        }
        
        if (!$show_pickup) {
            return;
        }
        
        ?>
        <tr class="cdek-pickup-selector">
            <td colspan="2">
                <div id="cdek-pickup-container">
                    <h4><?php _e('Выберите пункт выдачи CDEK', 'woocommerce-cdek-delivery'); ?></h4>
                    
                    <div class="cdek-search-container">
                        <input type="text" id="cdek-city-search" placeholder="<?php _e('Введите название города', 'woocommerce-cdek-delivery'); ?>" />
                        <button type="button" id="cdek-search-btn"><?php _e('Поиск', 'woocommerce-cdek-delivery'); ?></button>
                    </div>
                    
                    <div id="cdek-map-container" style="display: none;">
                        <div id="cdek-map" style="width: 100%; height: 400px;"></div>
                    </div>
                    
                    <div id="cdek-offices-list" style="display: none;">
                        <h5><?php _e('Доступные пункты выдачи:', 'woocommerce-cdek-delivery'); ?></h5>
                        <div class="cdek-offices-container"></div>
                    </div>
                    
                    <input type="hidden" id="cdek-selected-office" name="cdek_pickup_office" value="" />
                    <div id="cdek-selected-office-info" style="display: none;">
                        <h5><?php _e('Выбранный пункт выдачи:', 'woocommerce-cdek-delivery'); ?></h5>
                        <div class="cdek-office-details"></div>
                    </div>
                </div>
            </td>
        </tr>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Автоматически заполняем поле города из данных доставки
            var billingCity = $('#billing_city').val();
            var shippingCity = $('#shipping_city').val();
            var city = shippingCity || billingCity;
            
            if (city) {
                $('#cdek-city-search').val(city);
            }
            
            // Обработчик изменения способа доставки
            $(document.body).on('updated_checkout', function() {
                var selectedShipping = $('input[name^="shipping_method"]:checked').val();
                
                if (selectedShipping && selectedShipping.indexOf('cdek_pickup') !== -1) {
                    $('.cdek-pickup-selector').show();
                } else {
                    $('.cdek-pickup-selector').hide();
                }
            });
        });
        </script>
        
        <style>
        .cdek-pickup-selector {
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .cdek-search-container {
            margin: 15px 0;
        }
        
        .cdek-search-container input {
            width: 70%;
            padding: 8px;
            margin-right: 10px;
        }
        
        .cdek-search-container button {
            padding: 8px 15px;
            background: #0073aa;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .cdek-offices-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
        
        .cdek-office-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .cdek-office-item:hover {
            background-color: #f5f5f5;
        }
        
        .cdek-office-item.selected {
            background-color: #e3f2fd;
            border-left: 4px solid #0073aa;
        }
        
        .cdek-office-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .cdek-office-address {
            color: #666;
            margin-bottom: 5px;
        }
        
        .cdek-office-hours {
            font-size: 12px;
            color: #888;
        }
        
        .cdek-loading {
            text-align: center;
            padding: 20px;
        }
        
        .cdek-error {
            color: #d32f2f;
            padding: 10px;
            background: #ffebee;
            border: 1px solid #ffcdd2;
            margin: 10px 0;
        }
        
        #cdek-selected-office-info {
            background: #e8f5e8;
            padding: 15px;
            border: 1px solid #4caf50;
            margin: 15px 0;
            border-radius: 4px;
        }
        </style>
        <?php
    }
    
    /**
     * Save selected pickup point
     */
    public function save_pickup_point($order_id) {
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
     * Display pickup point in admin order page
     */
    public function display_pickup_point_in_admin($order) {
        $pickup_office = get_post_meta($order->get_id(), '_cdek_pickup_office', true);
        
        if ($pickup_office) {
            ?>
            <div class="cdek-pickup-info">
                <h4><?php _e('Пункт выдачи CDEK', 'woocommerce-cdek-delivery'); ?></h4>
                <p>
                    <strong><?php _e('Код:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['code']); ?><br>
                    <strong><?php _e('Адрес:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['address']); ?><br>
                    <?php if (!empty($pickup_office['phone'])): ?>
                        <strong><?php _e('Телефон:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['phone']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($pickup_office['work_time'])): ?>
                        <strong><?php _e('Время работы:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['work_time']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Display pickup point in email
     */
    public function display_pickup_point_in_email($order, $sent_to_admin, $plain_text, $email) {
        $pickup_office = get_post_meta($order->get_id(), '_cdek_pickup_office', true);
        
        if ($pickup_office) {
            if ($plain_text) {
                echo "\n" . __('Пункт выдачи CDEK:', 'woocommerce-cdek-delivery') . "\n";
                echo __('Код:', 'woocommerce-cdek-delivery') . ' ' . $pickup_office['code'] . "\n";
                echo __('Адрес:', 'woocommerce-cdek-delivery') . ' ' . $pickup_office['address'] . "\n";
                if (!empty($pickup_office['phone'])) {
                    echo __('Телефон:', 'woocommerce-cdek-delivery') . ' ' . $pickup_office['phone'] . "\n";
                }
                if (!empty($pickup_office['work_time'])) {
                    echo __('Время работы:', 'woocommerce-cdek-delivery') . ' ' . $pickup_office['work_time'] . "\n";
                }
            } else {
                ?>
                <h3><?php _e('Пункт выдачи CDEK', 'woocommerce-cdek-delivery'); ?></h3>
                <p>
                    <strong><?php _e('Код:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['code']); ?><br>
                    <strong><?php _e('Адрес:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['address']); ?><br>
                    <?php if (!empty($pickup_office['phone'])): ?>
                        <strong><?php _e('Телефон:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['phone']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($pickup_office['work_time'])): ?>
                        <strong><?php _e('Время работы:', 'woocommerce-cdek-delivery'); ?></strong> <?php echo esc_html($pickup_office['work_time']); ?>
                    <?php endif; ?>
                </p>
                <?php
            }
        }
    }
    
    /**
     * Add pickup point to shipping display
     */
    public function add_pickup_point_to_shipping_display($shipping_display, $order) {
        $pickup_office = get_post_meta($order->get_id(), '_cdek_pickup_office', true);
        
        if ($pickup_office) {
            $shipping_display .= '<br><small>' . __('Пункт выдачи:', 'woocommerce-cdek-delivery') . ' ' . esc_html($pickup_office['address']) . '</small>';
        }
        
        return $shipping_display;
    }
}

new WC_CDEK_Frontend();