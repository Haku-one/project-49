<?php
/**
 * CDEK API Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDEK_API {
    
    private $client_id;
    private $client_secret;
    private $base_url = 'https://api.cdek.ru/v2';
    private $test_url = 'https://api.edu.cdek.ru/v2';
    private $access_token;
    private $is_test_mode = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // You can enable test mode for development
        $this->is_test_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        if ($this->is_test_mode) {
            $this->base_url = $this->test_url;
        }
    }
    
    /**
     * Set API credentials
     */
    public function set_credentials($client_id, $client_secret) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }
    
    /**
     * Get access token
     */
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        // Check cache
        $cached_token = get_transient('cdek_access_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return $this->access_token;
        }
        
        // Get new token
        $response = wp_remote_post($this->base_url . '/oauth/token', array(
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('CDEK API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            
            // Cache token for 1 hour (expires in 3600 seconds)
            set_transient('cdek_access_token', $this->access_token, 3500);
            
            return $this->access_token;
        }
        
        error_log('CDEK API Error: Failed to get access token. Response: ' . $body);
        return false;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }
        
        $url = $this->base_url . $endpoint;
        if ($data && $method === 'GET') {
            $url .= '?' . http_build_query($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('CDEK API Request Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code >= 400) {
            error_log('CDEK API Error ' . $response_code . ': ' . $body);
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get delivery points by city
     */
    public function get_delivery_points($city) {
        // Try to get from cache first
        $cache_key = 'cdek_offices_' . md5($city);
        $cached_offices = get_transient($cache_key);
        
        if ($cached_offices !== false) {
            return $cached_offices;
        }
        
        // Search for city first
        $city_data = $this->search_city($city);
        if (!$city_data) {
            return $this->get_fallback_offices($city);
        }
        
        $city_code = $city_data[0]['code'] ?? null;
        if (!$city_code) {
            return $this->get_fallback_offices($city);
        }
        
        // Get delivery points for city
        $offices = $this->make_request('/deliverypoints', 'GET', array(
            'city_code' => $city_code,
            'type' => 'PVZ', // Пункты выдачи заказов
            'have_cash' => 'true',
            'have_cashless' => 'true',
            'is_handout' => 'true'
        ));
        
        if (!$offices) {
            return $this->get_fallback_offices($city);
        }
        
        // Format offices data
        $formatted_offices = array();
        foreach ($offices as $office) {
            $formatted_offices[] = array(
                'code' => $office['code'],
                'name' => $office['name'] ?? 'CDEK ' . $office['code'],
                'address' => $office['location']['address_full'] ?? $office['location']['address'] ?? '',
                'phone' => $office['phone'] ?? '',
                'work_time' => $this->format_work_time($office['work_time'] ?? []),
                'latitude' => $office['location']['latitude'] ?? null,
                'longitude' => $office['location']['longitude'] ?? null
            );
        }
        
        // Cache for 1 hour
        set_transient($cache_key, $formatted_offices, 3600);
        
        return $formatted_offices;
    }
    
    /**
     * Search city by name
     */
    private function search_city($city_name) {
        $response = $this->make_request('/location/cities', 'GET', array(
            'city' => $city_name,
            'country_codes' => 'RU'
        ));
        
        return $response;
    }
    
    /**
     * Format work time
     */
    private function format_work_time($work_time) {
        if (empty($work_time)) {
            return 'Уточняйте время работы';
        }
        
        $formatted = array();
        foreach ($work_time as $day) {
            $day_name = $this->get_day_name($day['day']);
            if ($day['time']) {
                $formatted[] = $day_name . ': ' . $day['time'];
            }
        }
        
        return implode(', ', $formatted);
    }
    
    /**
     * Get day name in Russian
     */
    private function get_day_name($day) {
        $days = array(
            1 => 'Пн',
            2 => 'Вт', 
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс'
        );
        
        return $days[$day] ?? $day;
    }
    
    /**
     * Calculate delivery cost
     */
    public function calculate_delivery_cost($from_city, $to_city, $weight, $office_code = null) {
        // Get city codes
        $from_city_data = $this->search_city($from_city);
        $to_city_data = $this->search_city($to_city);
        
        if (!$from_city_data || !$to_city_data) {
            return false;
        }
        
        $from_code = $from_city_data[0]['code'] ?? null;
        $to_code = $to_city_data[0]['code'] ?? null;
        
        if (!$from_code || !$to_code) {
            return false;
        }
        
        // Prepare calculation data
        $calculation_data = array(
            'type' => 1, // Интернет-магазин
            'from_location' => array(
                'code' => $from_code
            ),
            'to_location' => array(
                'code' => $to_code
            ),
            'packages' => array(
                array(
                    'weight' => intval($weight * 1000), // Convert to grams
                    'length' => 10,
                    'width' => 10,
                    'height' => 10
                )
            )
        );
        
        // Add office code if provided
        if ($office_code) {
            $calculation_data['to_location']['code'] = $office_code;
        }
        
        $response = $this->make_request('/calculator/tariff', 'POST', $calculation_data);
        
        if ($response && isset($response['delivery_sum'])) {
            return floatval($response['delivery_sum']);
        }
        
        // Fallback cost calculation
        return $this->calculate_fallback_cost($weight);
    }
    
    /**
     * Get fallback offices when API fails
     */
    private function get_fallback_offices($city) {
        return array(
            array(
                'code' => 'TEST001',
                'name' => 'CDEK Пункт выдачи №1',
                'address' => 'г. ' . $city . ', ул. Центральная, д. 1',
                'phone' => '+7 (800) 250-44-44',
                'work_time' => 'Пн-Пт: 9:00-21:00, Сб-Вс: 10:00-18:00',
                'latitude' => '55.755814',
                'longitude' => '37.617635'
            ),
            array(
                'code' => 'TEST002',
                'name' => 'CDEK Пункт выдачи №2',
                'address' => 'г. ' . $city . ', ул. Главная, д. 15',
                'phone' => '+7 (800) 250-44-44',
                'work_time' => 'Ежедневно: 8:00-22:00',
                'latitude' => '55.752023',
                'longitude' => '37.593038'
            ),
            array(
                'code' => 'TEST003',
                'name' => 'CDEK Пункт выдачи №3',
                'address' => 'г. ' . $city . ', пр. Ленина, д. 25',
                'phone' => '+7 (800) 250-44-44',
                'work_time' => 'Пн-Вс: 10:00-20:00',
                'latitude' => '55.753215',
                'longitude' => '37.622504'
            )
        );
    }
    
    /**
     * Calculate fallback cost
     */
    private function calculate_fallback_cost($weight) {
        // Simple weight-based calculation
        $base_cost = 200; // Base cost in rubles
        $weight_cost = $weight * 50; // 50 rubles per kg
        
        return $base_cost + $weight_cost;
    }
    
    /**
     * Create order (for future use)
     */
    public function create_order($order_data) {
        return $this->make_request('/orders', 'POST', $order_data);
    }
    
    /**
     * Get order status (for future use)
     */
    public function get_order_status($order_uuid) {
        return $this->make_request('/orders/' . $order_uuid);
    }
}
?>