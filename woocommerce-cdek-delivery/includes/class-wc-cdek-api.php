<?php
/**
 * CDEK API Class
 * 
 * Handles all interactions with CDEK API
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_CDEK_API {
    
    /**
     * API endpoints
     */
    const API_URL = 'https://api.cdek.ru/v2/';
    const TEST_API_URL = 'https://api.edu.cdek.ru/v2/';
    
    /**
     * API credentials
     */
    private $account;
    private $secure_password;
    private $test_mode;
    
    /**
     * Access token
     */
    private $access_token;
    private $token_expires;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->account = get_option('wc_cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        $this->secure_password = get_option('wc_cdek_secure_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        $this->test_mode = get_option('wc_cdek_test_mode', 'no') === 'yes';
        
        $this->access_token = get_transient('cdek_access_token');
        $this->token_expires = get_transient('cdek_token_expires');
    }
    
    /**
     * Get API URL
     */
    private function get_api_url() {
        return $this->test_mode ? self::TEST_API_URL : self::API_URL;
    }
    
    /**
     * Get access token
     */
    private function get_access_token() {
        if (!$this->access_token || time() >= $this->token_expires - 300) {
            $this->authenticate();
        }
        return $this->access_token;
    }
    
    /**
     * Authenticate with CDEK API
     */
    private function authenticate() {
        $url = $this->get_api_url() . 'oauth/token';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->account,
                'client_secret' => $this->secure_password
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('CDEK API Authentication Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->token_expires = time() + $data['expires_in'];
            
            set_transient('cdek_access_token', $this->access_token, $data['expires_in'] - 300);
            set_transient('cdek_token_expires', $this->token_expires, $data['expires_in'] - 300);
            
            return true;
        }
        
        error_log('CDEK API Authentication failed: ' . $body);
        return false;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return new WP_Error('no_token', 'Failed to get access token');
        }
        
        $url = $this->get_api_url() . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && ($method === 'POST' || $method === 'PUT')) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code >= 400) {
            return new WP_Error('api_error', 'API Error: ' . $body, array('response_code' => $response_code));
        }
        
        return $decoded;
    }
    
    /**
     * Get cities by name or postal code
     */
    public function get_cities($query, $limit = 20) {
        $cities = $this->make_request('location/cities?' . http_build_query(array(
            'city' => $query,
            'size' => $limit
        )));
        
        if (is_wp_error($cities)) {
            return array();
        }
        
        return $cities;
    }
    
    /**
     * Get delivery offices
     */
    public function get_offices($city_code = null, $postal_code = null) {
        $params = array();
        
        if ($city_code) {
            $params['city_code'] = $city_code;
        }
        
        if ($postal_code) {
            $params['postal_code'] = $postal_code;
        }
        
        $offices = $this->make_request('deliverypoints?' . http_build_query($params));
        
        if (is_wp_error($offices)) {
            return array();
        }
        
        return $offices;
    }
    
    /**
     * Calculate delivery cost
     */
    public function calculate_delivery_cost($from_location, $to_location, $weight, $dimensions = array(), $delivery_type = 'pickup') {
        // Определяем тариф в зависимости от типа доставки
        $tariff_codes = array(
            'pickup' => 136, // До постомата
            'door' => 233,   // До двери
            'office' => 138  // До пункта выдачи
        );
        
        $tariff_code = $tariff_codes[$delivery_type] ?? 136;
        
        // Подготавливаем данные для расчета
        $packages = array(
            array(
                'weight' => max($weight, 1), // Минимальный вес 1г
                'length' => max($dimensions['length'] ?? 1, 1),
                'width' => max($dimensions['width'] ?? 1, 1),
                'height' => max($dimensions['height'] ?? 1, 1)
            )
        );
        
        $data = array(
            'type' => 1, // Интернет-магазин
            'from_location' => $this->prepare_location($from_location),
            'to_location' => $this->prepare_location($to_location),
            'tariff_code' => $tariff_code,
            'packages' => $packages
        );
        
        $result = $this->make_request('calculator/tariff', 'POST', $data);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        if (isset($result['errors']) && !empty($result['errors'])) {
            return array(
                'success' => false,
                'error' => implode(', ', array_column($result['errors'], 'message'))
            );
        }
        
        return array(
            'success' => true,
            'cost' => $result['delivery_sum'] ?? 0,
            'period' => array(
                'min' => $result['period_min'] ?? 1,
                'max' => $result['period_max'] ?? 1
            ),
            'currency' => $result['currency'] ?? 'RUB'
        );
    }
    
    /**
     * Prepare location data
     */
    private function prepare_location($location) {
        if (is_array($location)) {
            return $location;
        }
        
        // Если передан код города
        if (is_numeric($location)) {
            return array('code' => intval($location));
        }
        
        // Если передан почтовый индекс
        if (preg_match('/^\d{6}$/', $location)) {
            return array('postal_code' => $location);
        }
        
        // Если передано название города
        return array('city' => $location);
    }
    
    /**
     * Create order
     */
    public function create_order($order_data) {
        $result = $this->make_request('orders', 'POST', $order_data);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        if (isset($result['errors']) && !empty($result['errors'])) {
            return array(
                'success' => false,
                'error' => implode(', ', array_column($result['errors'], 'message'))
            );
        }
        
        return array(
            'success' => true,
            'entity' => $result['entity'] ?? array()
        );
    }
    
    /**
     * Get order info
     */
    public function get_order($order_uuid) {
        $result = $this->make_request('orders/' . $order_uuid);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'entity' => $result['entity'] ?? array()
        );
    }
    
    /**
     * Get regions
     */
    public function get_regions($country_code = 'RU') {
        $regions = $this->make_request('location/regions?' . http_build_query(array(
            'country_codes' => $country_code,
            'size' => 1000
        )));
        
        if (is_wp_error($regions)) {
            return array();
        }
        
        return $regions;
    }
    
    /**
     * Search locations by query
     */
    public function search_locations($query) {
        // Поиск городов
        $cities = $this->get_cities($query, 10);
        
        $results = array();
        
        if (!empty($cities)) {
            foreach ($cities as $city) {
                $results[] = array(
                    'type' => 'city',
                    'code' => $city['code'],
                    'name' => $city['city'],
                    'region' => $city['region'] ?? '',
                    'country' => $city['country'] ?? 'Россия'
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get delivery points with map coordinates
     */
    public function get_delivery_points_with_map($city_query) {
        // Сначала попробуем найти город по названию
        $city_code = null;
        
        if (is_numeric($city_query)) {
            $city_code = $city_query;
        } else {
            // Ищем город по названию
            $cities = $this->get_cities($city_query, 1);
            if (!empty($cities) && isset($cities[0]['code'])) {
                $city_code = $cities[0]['code'];
            }
        }
        
        if (!$city_code) {
            return array();
        }
        
        $offices = $this->get_offices($city_code);
        
        if (empty($offices)) {
            return array();
        }
        
        $points = array();
        
        foreach ($offices as $office) {
            $points[] = array(
                'code' => $office['code'],
                'name' => $office['name'] ?? '',
                'address' => $office['location']['address_full'] ?? $office['location']['address'] ?? '',
                'phone' => $office['phone'] ?? '',
                'work_time' => is_array($office['work_time']) ? json_encode($office['work_time']) : ($office['work_time'] ?? ''),
                'latitude' => $office['location']['latitude'] ?? 0,
                'longitude' => $office['location']['longitude'] ?? 0,
                'type' => $office['type'] ?? 'PVZ',
                'owner_code' => $office['owner_code'] ?? 'cdek'
            );
        }
        
        return $points;
    }
}