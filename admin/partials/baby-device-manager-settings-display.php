<?php
if (!defined('ABSPATH')) {
    exit;
}

// 处理表单提交
if (isset($_POST['submit_settings'])) {
    // 验证nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_settings')) {
        wp_die('安全验证失败，请重试。');
    }

    $devices_per_row = intval($_POST['devices_per_row']);
    if ($devices_per_row < 1) $devices_per_row = 3;
    if ($devices_per_row > 6) $devices_per_row = 6;
    
    // Redis 设置
    $redis_enabled = isset($_POST['redis_enabled']) ? 1 : 0;
    $redis_host = sanitize_text_field($_POST['redis_host']);
    $redis_port = intval($_POST['redis_port']);
    $redis_password = sanitize_text_field($_POST['redis_password']);
    $redis_database = intval($_POST['redis_database']);
    
    update_option('bdm_devices_per_row', $devices_per_row);
    update_option('bdm_redis_enabled', $redis_enabled);
    update_option('bdm_redis_host', $redis_host);
    update_option('bdm_redis_port', $redis_port);
    update_option('bdm_redis_password', $redis_password);
    update_option('bdm_redis_database', $redis_database);
    
    echo '<div class="notice notice-success"><p>设置已保存！</p></div>';
}

// 处理缓存清理
if (isset($_POST['clear_cache']) && check_admin_referer('clear_cache')) {
    try {
        // 清空Redis缓存
        if (class_exists('Redis')) {
            $redis = new Redis();
            $redis->connect(
                get_option('bdm_redis_host', '127.0.0.1'),
                get_option('bdm_redis_port', 6379)
            );
            
            if (get_option('bdm_redis_password')) {
                $redis->auth(get_option('bdm_redis_password'));
            }
            
            if (get_option('bdm_redis_database')) {
                $redis->select(get_option('bdm_redis_database'));
            }
            
            $keys = $redis->keys('baby_device_manager_*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
            $redis->close();
        }
        
        // 清空WordPress对象缓存
        wp_cache_delete('baby_device_manager_groups', 'options');
        wp_cache_delete('baby_device_manager_devices', 'options');
        wp_cache_delete('baby_device_manager_settings', 'options');
        
        // 清空WordPress瞬态缓存
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_baby_device_manager_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_site_transient_baby_device_manager_%'");
        
        echo '<div class="notice notice-success"><p>缓存已清理！</p></div>';
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>清理缓存失败：' . esc_html($e->getMessage()) . '</p></div>';
    }
}

// 获取当前设置
$devices_per_row = get_option('bdm_devices_per_row', 3);
$redis_enabled = get_option('bdm_redis_enabled', 0);
$redis_host = get_option('bdm_redis_host', '127.0.0.1');
$redis_port = get_option('bdm_redis_port', 6379);
$redis_password = get_option('bdm_redis_password', '');
$redis_database = get_option('bdm_redis_database', 0);
?>

<div class="wrap">
    <h1>设备管理设置</h1>
    
    <div class="card">
        <form method="post" action="">
            <?php wp_nonce_field('save_settings', '_wpnonce', true); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="devices_per_row">每行显示设备数量</label></th>
                    <td>
                        <input type="number" name="devices_per_row" id="devices_per_row" 
                               class="small-text" min="1" max="6" 
                               value="<?php echo esc_attr($devices_per_row); ?>">
                        <p class="description">设置每行显示的设备数量（1-6个，默认为3个）</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Redis 缓存设置</th>
                    <td>
                        <label>
                            <input type="checkbox" name="redis_enabled" value="1" 
                                   <?php checked($redis_enabled, 1); ?>>
                            启用 Redis 缓存
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="redis_host">Redis 主机</label></th>
                    <td>
                        <input type="text" name="redis_host" id="redis_host" 
                               class="regular-text" value="<?php echo esc_attr($redis_host); ?>">
                        <p class="description">Redis 服务器地址，默认为 127.0.0.1</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="redis_port">Redis 端口</label></th>
                    <td>
                        <input type="number" name="redis_port" id="redis_port" 
                               class="small-text" value="<?php echo esc_attr($redis_port); ?>">
                        <p class="description">Redis 服务器端口，默认为 6379</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="redis_password">Redis 密码</label></th>
                    <td>
                        <input type="password" name="redis_password" id="redis_password" 
                               class="regular-text" value="<?php echo esc_attr($redis_password); ?>">
                        <p class="description">Redis 服务器密码（如果有）</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="redis_database">Redis 数据库</label></th>
                    <td>
                        <input type="number" name="redis_database" id="redis_database" 
                               class="small-text" value="<?php echo esc_attr($redis_database); ?>">
                        <p class="description">Redis 数据库编号，默认为 0</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit_settings" class="button button-primary" value="保存设置">
            </p>
        </form>
    </div>
    
    <div class="card">
        <h2>缓存管理</h2>
        <form method="post" action="">
            <?php wp_nonce_field('clear_cache'); ?>
            <p>点击下面的按钮清理所有缓存数据。</p>
            <p class="submit">
                <input type="submit" name="clear_cache" class="button" value="清理缓存" 
                       onclick="return confirm('确定要清理所有缓存吗？');">
            </p>
        </form>
    </div>
</div> 