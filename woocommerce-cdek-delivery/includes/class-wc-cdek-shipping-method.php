<?php
/**
 * CDEK Shipping Method
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_CDEK_Shipping_Method extends WC_Shipping_Method {
    
    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id = 'cdek';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('CDEK Доставка', 'woocommerce-cdek-delivery');
        $this->method_description = __('Метод доставки CDEK с пунктами выдачи и доставкой до двери', 'woocommerce-cdek-delivery');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        
        $this->init();
    }
    
    /**
     * Initialize
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Включить/Отключить', 'woocommerce-cdek-delivery'),
                'type' => 'checkbox',
                'label' => __('Включить доставку CDEK', 'woocommerce-cdek-delivery'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название', 'woocommerce-cdek-delivery'),
                'type' => 'text',
                'description' => __('Название метода доставки, которое видит пользователь при оформлении заказа.', 'woocommerce-cdek-delivery'),
                'default' => __('CDEK Доставка', 'woocommerce-cdek-delivery'),
                'desc_tip' => true,
            ),
            'account' => array(
                'title' => __('Account ID', 'woocommerce-cdek-delivery'),
                'type' => 'text',
                'description' => __('Your CDEK account identifier', 'woocommerce-cdek-delivery'),
                'default' => 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR',
                'desc_tip' => true,
            ),
            'secure_password' => array(
                'title' => __('Secure Password', 'woocommerce-cdek-delivery'),
                'type' => 'password',
                'description' => __('Your CDEK secure password', 'woocommerce-cdek-delivery'),
                'default' => 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM',
                'desc_tip' => true,
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'woocommerce-cdek-delivery'),
                'type' => 'checkbox',
                'label' => __('Enable test mode', 'woocommerce-cdek-delivery'),
                'default' => 'no',
                'description' => __('Use CDEK test API for development', 'woocommerce-cdek-delivery'),
            ),
            'sender_city' => array(
                'title' => __('Sender City Code', 'woocommerce-cdek-delivery'),
                'type' => 'text',
                'description' => __('CDEK city code for sender location (Saratov: 393)', 'woocommerce-cdek-delivery'),
                'default' => '393', // Саратов
                'desc_tip' => true,
            ),
            'pickup_enabled' => array(
                'title' => __('Pickup Points', 'woocommerce-cdek-delivery'),
                'type' => 'checkbox',
                'label' => __('Enable pickup from CDEK points', 'woocommerce-cdek-delivery'),
                'default' => 'yes'
            ),
            'door_enabled' => array(
                'title' => __('Door Delivery', 'woocommerce-cdek-delivery'),
                'type' => 'checkbox',
                'label' => __('Enable door-to-door delivery', 'woocommerce-cdek-delivery'),
                'default' => 'yes'
            ),
            'extra_cost' => array(
                'title' => __('Extra Cost', 'woocommerce-cdek-delivery'),
                'type' => 'number',
                'description' => __('Additional cost to add to CDEK calculated price', 'woocommerce-cdek-delivery'),
                'default' => '0',
                'desc_tip' => true,
            ),
            'extra_cost_percent' => array(
                'title' => __('Extra Cost Percent', 'woocommerce-cdek-delivery'),
                'type' => 'number',
                'description' => __('Additional cost as percentage of CDEK calculated price', 'woocommerce-cdek-delivery'),
                'default' => '0',
                'desc_tip' => true,
            ),
        );
    }
    
    /**
     * Calculate shipping
     */
    public function calculate_shipping($package = array()) {
        // Получаем настройки
        $sender_city = $this->get_option('sender_city', '393');
        $pickup_enabled = $this->get_option('pickup_enabled') === 'yes';
        $door_enabled = $this->get_option('door_enabled') === 'yes';
        $extra_cost = floatval($this->get_option('extra_cost', 0));
        $extra_cost_percent = floatval($this->get_option('extra_cost_percent', 0));
        
        // Получаем данные получателя
        $destination = $package['destination'];
        $city = $destination['city'] ?? '';
        $postcode = $destination['postcode'] ?? '';
        
        if (empty($city) && empty($postcode)) {
            return;
        }
        
        // Рассчитываем общий вес и размеры
        $total_weight = 0;
        $total_volume = 0;
        $max_length = 0;
        $max_width = 0;
        $max_height = 0;
        
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $quantity = $item['quantity'];
            
            $weight = floatval($product->get_weight() ?: 100); // вес в граммах, по умолчанию 100г
            $length = floatval($product->get_length() ?: 10);
            $width = floatval($product->get_width() ?: 10);
            $height = floatval($product->get_height() ?: 10);
            
            $total_weight += $weight * $quantity;
            $total_volume += ($length * $width * $height) * $quantity;
            
            $max_length = max($max_length, $length);
            $max_width = max($max_width, $width);
            $max_height = max($max_height, $height);
        }
        
        // Конвертируем размеры в см если они в м
        if ($max_length < 1) {
            $max_length *= 100;
            $max_width *= 100;
            $max_height *= 100;
        }
        
        $dimensions = array(
            'length' => max($max_length, 1),
            'width' => max($max_width, 1),
            'height' => max($max_height, 1)
        );
        
        // Определяем город получателя
        $to_location = !empty($postcode) ? $postcode : $city;
        
        // Инициализируем API
        $api = new WC_CDEK_API();
        
        $rates = array();
        
        // Рассчитываем стоимость для самовывоза
        if ($pickup_enabled) {
            $result = $api->calculate_delivery_cost($sender_city, $to_location, $total_weight, $dimensions, 'pickup');
            
            if ($result['success']) {
                $cost = $result['cost'];
                $cost += $extra_cost;
                $cost += ($cost * $extra_cost_percent / 100);
                
                $period_text = '';
                if (isset($result['period'])) {
                    $min_days = $result['period']['min'];
                    $max_days = $result['period']['max'];
                    if ($min_days == $max_days) {
                        $period_text = sprintf(' (%d дн.)', $min_days);
                    } else {
                        $period_text = sprintf(' (%d-%d дн.)', $min_days, $max_days);
                    }
                }
                
                $rates[] = array(
                    'id' => $this->id . '_pickup',
                    'label' => __('CDEK Pickup Point', 'woocommerce-cdek-delivery') . $period_text,
                    'cost' => $cost,
                    'meta_data' => array(
                        'delivery_type' => 'pickup',
                        'period_min' => $result['period']['min'] ?? 1,
                        'period_max' => $result['period']['max'] ?? 1
                    )
                );
            }
        }
        
        // Рассчитываем стоимость для доставки до двери
        if ($door_enabled) {
            $result = $api->calculate_delivery_cost($sender_city, $to_location, $total_weight, $dimensions, 'door');
            
            if ($result['success']) {
                $cost = $result['cost'];
                $cost += $extra_cost;
                $cost += ($cost * $extra_cost_percent / 100);
                
                $period_text = '';
                if (isset($result['period'])) {
                    $min_days = $result['period']['min'];
                    $max_days = $result['period']['max'];
                    if ($min_days == $max_days) {
                        $period_text = sprintf(' (%d дн.)', $min_days);
                    } else {
                        $period_text = sprintf(' (%d-%d дн.)', $min_days, $max_days);
                    }
                }
                
                $rates[] = array(
                    'id' => $this->id . '_door',
                    'label' => __('CDEK Door Delivery', 'woocommerce-cdek-delivery') . $period_text,
                    'cost' => $cost,
                    'meta_data' => array(
                        'delivery_type' => 'door',
                        'period_min' => $result['period']['min'] ?? 1,
                        'period_max' => $result['period']['max'] ?? 1
                    )
                );
            }
        }
        
        // Добавляем тарифы
        foreach ($rates as $rate) {
            $this->add_rate($rate);
        }
    }
    
    /**
     * Process admin options
     */
    public function process_admin_options() {
        parent::process_admin_options();
        
        // Сохраняем настройки в опции WordPress для использования в API
        update_option('wc_cdek_account', $this->get_option('account'));
        update_option('wc_cdek_secure_password', $this->get_option('secure_password'));
        update_option('wc_cdek_test_mode', $this->get_option('test_mode'));
        update_option('wc_cdek_sender_city', $this->get_option('sender_city'));
        
        // Очищаем кэш токена при изменении настроек
        delete_transient('cdek_access_token');
        delete_transient('cdek_token_expires');
    }
    
    /**
     * Admin options
     */
    public function admin_options() {
        ?>
        <h3><?php echo $this->method_title; ?></h3>
        <p><?php echo $this->method_description; ?></p>
        
        <div class="cdek-settings-notice">
            <h4><?php _e('Setup Instructions:', 'woocommerce-cdek-delivery'); ?></h4>
            <ol>
                <li><?php _e('Enter your CDEK API credentials', 'woocommerce-cdek-delivery'); ?></li>
                <li><?php _e('Set your sender city code (for Saratov use: 393)', 'woocommerce-cdek-delivery'); ?></li>
                <li><?php _e('Configure delivery options (pickup points and/or door delivery)', 'woocommerce-cdek-delivery'); ?></li>
                <li><?php _e('Test the integration on checkout page', 'woocommerce-cdek-delivery'); ?></li>
            </ol>
        </div>
        
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#woocommerce_cdek_test_mode').change(function() {
                if ($(this).is(':checked')) {
                    alert('<?php _e('Test mode is enabled. Remember to disable it in production!', 'woocommerce-cdek-delivery'); ?>');
                }
            });
        });
        </script>
        <?php
    }
}