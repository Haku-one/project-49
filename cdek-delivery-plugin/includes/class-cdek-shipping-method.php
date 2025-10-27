<?php
/**
 * CDEK Shipping Method Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_CDEK_Shipping_Method extends WC_Shipping_Method {
    
    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id = 'cdek_delivery';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('CDEK Доставка', 'cdek-delivery');
        $this->method_description = __('Доставка через службу CDEK с выбором пунктов выдачи', 'cdek-delivery');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        $this->init();
    }
    
    /**
     * Initialize the shipping method
     */
    public function init() {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->cost = $this->get_option('cost');
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->sender_city = $this->get_option('sender_city');
        $this->default_weight = $this->get_option('default_weight');
        $this->free_shipping_min = $this->get_option('free_shipping_min');
        
        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Включить', 'cdek-delivery'),
                'type' => 'checkbox',
                'description' => __('Включить метод доставки CDEK', 'cdek-delivery'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Название метода доставки, которое увидят покупатели', 'cdek-delivery'),
                'default' => __('CDEK Доставка', 'cdek-delivery'),
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Фиксированная стоимость', 'cdek-delivery'),
                'type' => 'price',
                'description' => __('Фиксированная стоимость доставки (если не используется расчет через API)', 'cdek-delivery'),
                'default' => '300',
                'desc_tip' => true,
            ),
            'client_id' => array(
                'title' => __('Client ID CDEK', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Идентификатор клиента для API CDEK', 'cdek-delivery'),
                'default' => '',
                'desc_tip' => true,
            ),
            'client_secret' => array(
                'title' => __('Client Secret CDEK', 'cdek-delivery'),
                'type' => 'password',
                'description' => __('Секретный ключ для API CDEK', 'cdek-delivery'),
                'default' => '',
                'desc_tip' => true,
            ),
            'sender_city' => array(
                'title' => __('Город отправки', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Код города отправки в системе CDEK', 'cdek-delivery'),
                'default' => '44', // Москва
                'desc_tip' => true,
            ),
            'default_weight' => array(
                'title' => __('Вес по умолчанию (кг)', 'cdek-delivery'),
                'type' => 'decimal',
                'description' => __('Вес товара по умолчанию для расчета доставки', 'cdek-delivery'),
                'default' => '1',
                'desc_tip' => true,
            ),
            'free_shipping_min' => array(
                'title' => __('Бесплатная доставка от суммы', 'cdek-delivery'),
                'type' => 'price',
                'description' => __('Минимальная сумма заказа для бесплатной доставки (0 - отключить)', 'cdek-delivery'),
                'default' => '0',
                'desc_tip' => true,
            ),
        );
    }
    
    /**
     * Calculate shipping cost
     */
    public function calculate_shipping($package = array()) {
        $cost = 0;
        $total = WC()->cart->get_subtotal();
        
        // Check for free shipping
        if ($this->free_shipping_min > 0 && $total >= $this->free_shipping_min) {
            $cost = 0;
        } else {
            // Try to calculate via API
            if (!empty($this->client_id) && !empty($this->client_secret)) {
                $api_cost = $this->calculate_api_cost($package);
                if ($api_cost !== false) {
                    $cost = $api_cost;
                } else {
                    $cost = $this->cost;
                }
            } else {
                $cost = $this->cost;
            }
        }
        
        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package,
        );
        
        $this->add_rate($rate);
    }
    
    /**
     * Calculate cost via CDEK API
     */
    private function calculate_api_cost($package) {
        if (!class_exists('CDEK_API')) {
            return false;
        }
        
        $api = new CDEK_API();
        $api->set_credentials($this->client_id, $this->client_secret);
        
        // Get destination city from package
        $destination_city = $package['destination']['city'] ?? '';
        if (empty($destination_city)) {
            return false;
        }
        
        // Calculate total weight
        $weight = 0;
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $product_weight = $product->get_weight();
            if ($product_weight) {
                $weight += $product_weight * $item['quantity'];
            } else {
                $weight += $this->default_weight * $item['quantity'];
            }
        }
        
        if ($weight <= 0) {
            $weight = $this->default_weight;
        }
        
        // Calculate via API
        return $api->calculate_delivery_cost($this->sender_city, $destination_city, $weight);
    }
    
    /**
     * Check if shipping method is available
     */
    public function is_available($package) {
        if ($this->enabled === 'no') {
            return false;
        }
        
        return parent::is_available($package);
    }
}
?>