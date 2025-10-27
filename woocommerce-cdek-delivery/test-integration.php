<?php
/**
 * Test file for CDEK Blocks Integration
 * This file helps test the integration with WooCommerce Blocks
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test CDEK Blocks Integration
 */
function test_cdek_blocks_integration() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return array(
            'status' => 'error',
            'message' => 'WooCommerce не активен'
        );
    }
    
    // Check if WooCommerce Blocks is available
    if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
        return array(
            'status' => 'warning',
            'message' => 'WooCommerce Blocks не найден - блочное оформление может не работать'
        );
    }
    
    // Check if checkout page uses blocks
    $checkout_page_id = wc_get_page_id('checkout');
    if ($checkout_page_id) {
        $checkout_content = get_post_field('post_content', $checkout_page_id);
        $uses_blocks = has_block('woocommerce/checkout', $checkout_content);
        
        if ($uses_blocks) {
            return array(
                'status' => 'success',
                'message' => 'Блочное оформление заказов активно и настроено'
            );
        } else {
            return array(
                'status' => 'info',
                'message' => 'Используется классическое оформление заказов'
            );
        }
    }
    
    return array(
        'status' => 'error',
        'message' => 'Страница оформления заказов не найдена'
    );
}

/**
 * Test CDEK API Connection
 */
function test_cdek_api_connection() {
    if (!class_exists('WC_CDEK_API')) {
        return array(
            'status' => 'error',
            'message' => 'Класс WC_CDEK_API не найден'
        );
    }
    
    $api = new WC_CDEK_API();
    
    // Test getting offices for Saratov
    $offices = $api->get_delivery_points_with_map('Саратов');
    
    if (is_array($offices) && !empty($offices)) {
        return array(
            'status' => 'success',
            'message' => 'API CDEK работает корректно. Найдено пунктов выдачи: ' . count($offices)
        );
    } else {
        return array(
            'status' => 'error',
            'message' => 'Ошибка подключения к API CDEK'
        );
    }
}

/**
 * Test Yandex Maps API
 */
function test_yandex_maps_api() {
    // Check if the API key is set
    $api_key = '4020b4d5-1d96-476c-a10e-8ab18f0f3702';
    
    if (empty($api_key)) {
        return array(
            'status' => 'error',
            'message' => 'API ключ Yandex Maps не настроен'
        );
    }
    
    // Test API availability (simplified check)
    $response = wp_remote_get("https://api-maps.yandex.ru/2.1/?apikey={$api_key}&lang=ru_RU");
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        return array(
            'status' => 'success',
            'message' => 'API Yandex Maps доступен'
        );
    } else {
        return array(
            'status' => 'warning',
            'message' => 'Не удалось проверить доступность API Yandex Maps'
        );
    }
}

/**
 * Display test results in admin
 */
function cdek_display_test_results() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    echo '<div class="wrap">';
    echo '<h1>Тест интеграции CDEK</h1>';
    
    $tests = array(
        'Блочное оформление заказов' => test_cdek_blocks_integration(),
        'API CDEK' => test_cdek_api_connection(),
        'API Yandex Maps' => test_yandex_maps_api()
    );
    
    foreach ($tests as $test_name => $result) {
        $class = '';
        switch ($result['status']) {
            case 'success':
                $class = 'notice-success';
                break;
            case 'warning':
                $class = 'notice-warning';
                break;
            case 'error':
                $class = 'notice-error';
                break;
            case 'info':
                $class = 'notice-info';
                break;
        }
        
        echo '<div class="notice ' . $class . '">';
        echo '<p><strong>' . $test_name . ':</strong> ' . $result['message'] . '</p>';
        echo '</div>';
    }
    
    echo '</div>';
}

// Add admin menu for testing
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Тест CDEK',
        'Тест CDEK',
        'manage_woocommerce',
        'cdek-test',
        'cdek_display_test_results'
    );
});

/**
 * Add test information to plugin row
 */
add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $test_link = admin_url('admin.php?page=cdek-test');
        $links[] = '<a href="' . $test_link . '">Тест интеграции</a>';
    }
    return $links;
}, 10, 2);