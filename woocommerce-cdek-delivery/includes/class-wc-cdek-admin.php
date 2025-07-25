<?php
/**
 * CDEK Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_CDEK_Admin {
    
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
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        add_action('wp_ajax_cdek_create_order', array($this, 'ajax_create_order'));
        add_action('wp_ajax_cdek_get_order_status', array($this, 'ajax_get_order_status'));
        add_action('wp_ajax_cdek_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cdek_sync_offices', array($this, 'ajax_sync_offices'));
        add_action('woocommerce_order_status_processing', array($this, 'auto_create_cdek_order'));
        add_filter('manage_shop_order_posts_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_columns'), 10, 2);
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('CDEK Delivery', 'woocommerce-cdek-delivery'),
            __('CDEK Delivery', 'woocommerce-cdek-delivery'),
            'manage_woocommerce',
            'wc-cdek-delivery',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('CDEK Delivery Settings', 'woocommerce-cdek-delivery'); ?></h1>
            
            <div class="cdek-admin-container">
                <div class="cdek-admin-section">
                    <h2><?php _e('Connection Status', 'woocommerce-cdek-delivery'); ?></h2>
                    <div id="cdek-connection-status">
                        <button type="button" id="test-connection" class="button button-primary">
                            <?php _e('Test API Connection', 'woocommerce-cdek-delivery'); ?>
                        </button>
                        <div id="connection-result"></div>
                    </div>
                </div>
                
                <div class="cdek-admin-section">
                    <h2><?php _e('Quick Actions', 'woocommerce-cdek-delivery'); ?></h2>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" class="button">
                            <?php _e('Configure Shipping Settings', 'woocommerce-cdek-delivery'); ?>
                        </a>
                    </p>
                    <p>
                        <button type="button" id="sync-offices" class="button">
                            <?php _e('Sync Pickup Points', 'woocommerce-cdek-delivery'); ?>
                        </button>
                    </p>
                </div>
                
                <div class="cdek-admin-section">
                    <h2><?php _e('Statistics', 'woocommerce-cdek-delivery'); ?></h2>
                    <?php $this->display_statistics(); ?>
                </div>
                
                <div class="cdek-admin-section">
                    <h2><?php _e('Recent Orders with CDEK Delivery', 'woocommerce-cdek-delivery'); ?></h2>
                    <?php $this->display_recent_orders(); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').click(function() {
                var button = $(this);
                var result = $('#connection-result');
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'woocommerce-cdek-delivery'); ?>');
                result.html('<p><?php _e('Testing connection...', 'woocommerce-cdek-delivery'); ?></p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cdek_test_connection',
                        nonce: '<?php echo wp_create_nonce('cdek_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error"><p><?php _e('Connection test failed', 'woocommerce-cdek-delivery'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test API Connection', 'woocommerce-cdek-delivery'); ?>');
                    }
                });
            });
            
            $('#sync-offices').click(function() {
                var button = $(this);
                
                button.prop('disabled', true).text('<?php _e('Syncing...', 'woocommerce-cdek-delivery'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cdek_sync_offices',
                        nonce: '<?php echo wp_create_nonce('cdek_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('Sync failed', 'woocommerce-cdek-delivery'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Sync Pickup Points', 'woocommerce-cdek-delivery'); ?>');
                    }
                });
            });
        });
        </script>
        
        <style>
        .cdek-admin-container {
            max-width: 1200px;
        }
        
        .cdek-admin-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 20px 0;
            padding: 20px;
        }
        
        .cdek-admin-section h2 {
            margin-top: 0;
        }
        
        #connection-result {
            margin-top: 15px;
        }
        
        .cdek-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .cdek-stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        .cdek-stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        
        .cdek-stat-label {
            color: #666;
            margin-top: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Display statistics
     */
    private function display_statistics() {
        global $wpdb;
        
        // Получаем статистику заказов
        $total_orders = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_shipping_method' 
            AND pm.meta_value LIKE '%cdek%' 
            AND p.post_type = 'shop_order'
        ");
        
        $pickup_orders = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_shipping_method' 
            AND pm.meta_value LIKE '%cdek_pickup%' 
            AND p.post_type = 'shop_order'
        ");
        
        $door_orders = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_shipping_method' 
            AND pm.meta_value LIKE '%cdek_door%' 
            AND p.post_type = 'shop_order'
        ");
        
        $cdek_orders = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_cdek_order_uuid'
        ");
        
        ?>
        <div class="cdek-stats-grid">
            <div class="cdek-stat-card">
                <div class="cdek-stat-number"><?php echo $total_orders; ?></div>
                <div class="cdek-stat-label"><?php _e('Total CDEK Orders', 'woocommerce-cdek-delivery'); ?></div>
            </div>
            <div class="cdek-stat-card">
                <div class="cdek-stat-number"><?php echo $pickup_orders; ?></div>
                <div class="cdek-stat-label"><?php _e('Pickup Orders', 'woocommerce-cdek-delivery'); ?></div>
            </div>
            <div class="cdek-stat-card">
                <div class="cdek-stat-number"><?php echo $door_orders; ?></div>
                <div class="cdek-stat-label"><?php _e('Door Delivery Orders', 'woocommerce-cdek-delivery'); ?></div>
            </div>
            <div class="cdek-stat-card">
                <div class="cdek-stat-number"><?php echo $cdek_orders; ?></div>
                <div class="cdek-stat-label"><?php _e('Created in CDEK', 'woocommerce-cdek-delivery'); ?></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display recent orders
     */
    private function display_recent_orders() {
        global $wpdb;
        
        $orders = $wpdb->get_results("
            SELECT p.ID, p.post_date 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_shipping_method' 
            AND pm.meta_value LIKE '%cdek%' 
            AND p.post_type = 'shop_order'
            ORDER BY p.post_date DESC 
            LIMIT 10
        ");
        
        if ($orders) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . __('Order', 'woocommerce-cdek-delivery') . '</th><th>' . __('Date', 'woocommerce-cdek-delivery') . '</th><th>' . __('Status', 'woocommerce-cdek-delivery') . '</th><th>' . __('CDEK Status', 'woocommerce-cdek-delivery') . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($orders as $order_data) {
                $order = wc_get_order($order_data->ID);
                $cdek_uuid = get_post_meta($order_data->ID, '_cdek_order_uuid', true);
                
                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $order_data->ID . '&action=edit') . '">#' . $order->get_order_number() . '</a></td>';
                echo '<td>' . date_i18n(get_option('date_format'), strtotime($order_data->post_date)) . '</td>';
                echo '<td>' . wc_get_order_status_name($order->get_status()) . '</td>';
                echo '<td>' . ($cdek_uuid ? __('Created', 'woocommerce-cdek-delivery') : __('Not created', 'woocommerce-cdek-delivery')) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No CDEK orders found.', 'woocommerce-cdek-delivery') . '</p>';
        }
    }
    
    /**
     * Add order meta boxes
     */
    public function add_order_meta_boxes() {
        add_meta_box(
            'cdek-order-actions',
            __('CDEK Delivery', 'woocommerce-cdek-delivery'),
            array($this, 'order_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Order meta box
     */
    public function order_meta_box($post) {
        $order = wc_get_order($post->ID);
        $shipping_methods = $order->get_shipping_methods();
        $is_cdek = false;
        
        foreach ($shipping_methods as $method) {
            if (strpos($method->get_method_id(), 'cdek') !== false) {
                $is_cdek = true;
                break;
            }
        }
        
        if (!$is_cdek) {
            echo '<p>' . __('This order does not use CDEK delivery.', 'woocommerce-cdek-delivery') . '</p>';
            return;
        }
        
        $cdek_uuid = get_post_meta($post->ID, '_cdek_order_uuid', true);
        $cdek_number = get_post_meta($post->ID, '_cdek_order_number', true);
        
        ?>
        <div class="cdek-order-meta">
            <?php if ($cdek_uuid): ?>
                <p><strong><?php _e('CDEK Order UUID:', 'woocommerce-cdek-delivery'); ?></strong><br>
                <code><?php echo esc_html($cdek_uuid); ?></code></p>
                
                <?php if ($cdek_number): ?>
                    <p><strong><?php _e('CDEK Order Number:', 'woocommerce-cdek-delivery'); ?></strong><br>
                    <?php echo esc_html($cdek_number); ?></p>
                <?php endif; ?>
                
                <p>
                    <button type="button" class="button" onclick="updateCdekOrderStatus(<?php echo $post->ID; ?>)">
                        <?php _e('Update Status', 'woocommerce-cdek-delivery'); ?>
                    </button>
                </p>
            <?php else: ?>
                <p><?php _e('Order not yet created in CDEK system.', 'woocommerce-cdek-delivery'); ?></p>
                <p>
                    <button type="button" class="button button-primary" onclick="createCdekOrder(<?php echo $post->ID; ?>)">
                        <?php _e('Create CDEK Order', 'woocommerce-cdek-delivery'); ?>
                    </button>
                </p>
            <?php endif; ?>
        </div>
        
        <script>
        function createCdekOrder(orderId) {
            if (!confirm('<?php _e('Create order in CDEK system?', 'woocommerce-cdek-delivery'); ?>')) {
                return;
            }
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cdek_create_order',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('cdek_admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Order created successfully!', 'woocommerce-cdek-delivery'); ?>');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        }
        
        function updateCdekOrderStatus(orderId) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cdek_get_order_status',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('cdek_admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Status updated!', 'woocommerce-cdek-delivery'); ?>');
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * AJAX create order
     */
    public function ajax_create_order() {
        check_ajax_referer('cdek_admin', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Создаем заказ в CDEK
        $result = $this->create_cdek_order($order);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Order created successfully'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Create CDEK order
     */
    private function create_cdek_order($order) {
        $api = new WC_CDEK_API();
        
        // Подготавливаем данные заказа
        $order_data = array(
            'number' => $order->get_order_number(),
            'tariff_code' => $this->get_tariff_code($order),
            'from_location' => array(
                'code' => get_option('wc_cdek_sender_city', '393')
            ),
            'to_location' => $this->get_recipient_location($order),
            'packages' => $this->get_packages($order),
            'recipient' => $this->get_recipient_data($order),
            'sender' => $this->get_sender_data()
        );
        
        // Добавляем адрес пункта выдачи если выбран самовывоз
        $pickup_office = get_post_meta($order->get_id(), '_cdek_pickup_office', true);
        if ($pickup_office) {
            $order_data['delivery_point'] = $pickup_office['code'];
        }
        
        $result = $api->create_order($order_data);
        
        if ($result['success'] && isset($result['entity']['uuid'])) {
            update_post_meta($order->get_id(), '_cdek_order_uuid', $result['entity']['uuid']);
            
            if (isset($result['entity']['cdek_number'])) {
                update_post_meta($order->get_id(), '_cdek_order_number', $result['entity']['cdek_number']);
            }
        }
        
        return $result;
    }
    
    /**
     * Get tariff code based on shipping method
     */
    private function get_tariff_code($order) {
        $shipping_methods = $order->get_shipping_methods();
        
        foreach ($shipping_methods as $method) {
            if (strpos($method->get_method_id(), 'cdek_pickup') !== false) {
                return 136; // До постомата
            } elseif (strpos($method->get_method_id(), 'cdek_door') !== false) {
                return 233; // До двери
            }
        }
        
        return 136; // По умолчанию
    }
    
    /**
     * Get recipient location
     */
    private function get_recipient_location($order) {
        $postcode = $order->get_shipping_postcode();
        $city = $order->get_shipping_city();
        
        if ($postcode) {
            return array('postal_code' => $postcode);
        } elseif ($city) {
            return array('city' => $city);
        }
        
        return array();
    }
    
    /**
     * Get packages data
     */
    private function get_packages($order) {
        $packages = array();
        $total_weight = 0;
        $total_value = 0;
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            
            $weight = floatval($product->get_weight() ?: 100);
            $total_weight += $weight * $quantity;
            $total_value += $item->get_total();
        }
        
        $packages[] = array(
            'number' => '1',
            'weight' => max($total_weight, 1),
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'cost' => $total_value,
            'comment' => 'WooCommerce order #' . $order->get_order_number()
        );
        
        return $packages;
    }
    
    /**
     * Get recipient data
     */
    private function get_recipient_data($order) {
        return array(
            'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'phones' => array(
                array('number' => preg_replace('/[^0-9+]/', '', $order->get_billing_phone()))
            ),
            'email' => $order->get_billing_email()
        );
    }
    
    /**
     * Get sender data
     */
    private function get_sender_data() {
        return array(
            'name' => 'Костин Сергей Владимирович',
            'phones' => array(
                array('number' => '+79873080200')
            ),
            'email' => 'costin.serzh2014@yandex.ru'
        );
    }
    
    /**
     * Auto create CDEK order when order status changes to processing
     */
    public function auto_create_cdek_order($order_id) {
        $order = wc_get_order($order_id);
        
        // Проверяем, используется ли CDEK доставка
        $shipping_methods = $order->get_shipping_methods();
        $is_cdek = false;
        
        foreach ($shipping_methods as $method) {
            if (strpos($method->get_method_id(), 'cdek') !== false) {
                $is_cdek = true;
                break;
            }
        }
        
        if (!$is_cdek) {
            return;
        }
        
        // Проверяем, не создан ли уже заказ
        $cdek_uuid = get_post_meta($order_id, '_cdek_order_uuid', true);
        if ($cdek_uuid) {
            return;
        }
        
        // Создаем заказ
        $this->create_cdek_order($order);
    }
    
    /**
     * Add order columns
     */
    public function add_order_columns($columns) {
        $columns['cdek_status'] = __('CDEK Status', 'woocommerce-cdek-delivery');
        return $columns;
    }
    
    /**
     * Display order columns
     */
    public function display_order_columns($column, $post_id) {
        if ($column === 'cdek_status') {
            $order = wc_get_order($post_id);
            $shipping_methods = $order->get_shipping_methods();
            $is_cdek = false;
            
            foreach ($shipping_methods as $method) {
                if (strpos($method->get_method_id(), 'cdek') !== false) {
                    $is_cdek = true;
                    break;
                }
            }
            
            if ($is_cdek) {
                $cdek_uuid = get_post_meta($post_id, '_cdek_order_uuid', true);
                if ($cdek_uuid) {
                    echo '<span style="color: green;">✓ ' . __('Created', 'woocommerce-cdek-delivery') . '</span>';
                } else {
                    echo '<span style="color: orange;">○ ' . __('Pending', 'woocommerce-cdek-delivery') . '</span>';
                }
            } else {
                echo '—';
            }
        }
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('cdek_admin', 'nonce');
        
        $api = new WC_CDEK_API();
        
        // Попробуем получить список городов
        $cities = $api->get_cities('Москва', 1);
        
        if (!empty($cities)) {
            wp_send_json_success(array(
                'message' => 'Соединение с CDEK API успешно установлено!'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Не удалось подключиться к CDEK API. Проверьте настройки.'
            ));
        }
    }
    
    /**
     * AJAX sync offices
     */
    public function ajax_sync_offices() {
        check_ajax_referer('cdek_admin', 'nonce');
        
        $api = new WC_CDEK_API();
        
        // Получаем пункты выдачи для основных городов
        $cities = array('393', '44', '270'); // Саратов, Москва, СПб
        $total_synced = 0;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdek_offices';
        
        foreach ($cities as $city_code) {
            $offices = $api->get_offices($city_code);
            
            if (!empty($offices)) {
                foreach ($offices as $office) {
                    $result = $wpdb->replace(
                        $table_name,
                        array(
                            'office_code' => $office['code'],
                            'city' => $office['location']['city'] ?? '',
                            'address' => $office['location']['address_full'] ?? $office['location']['address'] ?? '',
                            'phone' => $office['phone'] ?? '',
                            'work_time' => is_array($office['work_time']) ? json_encode($office['work_time']) : $office['work_time'],
                            'latitude' => $office['location']['latitude'] ?? 0,
                            'longitude' => $office['location']['longitude'] ?? 0
                        ),
                        array('%s', '%s', '%s', '%s', '%s', '%f', '%f')
                    );
                    
                    if ($result !== false) {
                        $total_synced++;
                    }
                }
            }
        }
        
        if ($total_synced > 0) {
            wp_send_json_success(array(
                'message' => sprintf('Синхронизировано %d пунктов выдачи', $total_synced)
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Не удалось синхронизировать пункты выдачи'
            ));
        }
    }
    
    /**
     * AJAX get order status
     */
    public function ajax_get_order_status() {
        check_ajax_referer('cdek_admin', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $cdek_uuid = get_post_meta($order_id, '_cdek_order_uuid', true);
        
        if (!$cdek_uuid) {
            wp_send_json_error(array('message' => 'CDEK UUID не найден'));
        }
        
        $api = new WC_CDEK_API();
        $result = $api->get_order($cdek_uuid);
        
        if ($result['success']) {
            $entity = $result['entity'];
            
            // Обновляем мета-данные заказа
            if (isset($entity['statuses']) && !empty($entity['statuses'])) {
                $latest_status = end($entity['statuses']);
                update_post_meta($order_id, '_cdek_status', $latest_status['code']);
                update_post_meta($order_id, '_cdek_status_name', $latest_status['name']);
            }
            
            if (isset($entity['cdek_number'])) {
                update_post_meta($order_id, '_cdek_order_number', $entity['cdek_number']);
            }
            
            wp_send_json_success(array(
                'message' => 'Статус заказа обновлен',
                'reload' => true
            ));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
}

new WC_CDEK_Admin();