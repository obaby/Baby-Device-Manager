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

// 处理 Redis 连接测试
if (isset($_POST['test_redis']) && check_admin_referer('test_redis')) {
    try {
        if (!class_exists('Redis')) {
            throw new Exception('Redis 扩展未安装');
        }

        $redis = new Redis();
        $connected = $redis->connect(
            get_option('bdm_redis_host', '127.0.0.1'),
            get_option('bdm_redis_port', 6379)
        );

        if (!$connected) {
            throw new Exception('无法连接到 Redis 服务器');
        }

        if (get_option('bdm_redis_password')) {
            if (!$redis->auth(get_option('bdm_redis_password'))) {
                throw new Exception('Redis 密码验证失败');
            }
        }

        if (get_option('bdm_redis_database')) {
            if (!$redis->select(get_option('bdm_redis_database'))) {
                throw new Exception('无法选择指定的 Redis 数据库');
            }
        }

        // 测试写入和读取
        $test_key = 'baby_device_manager_test_' . time();
        $test_value = 'test_' . uniqid();
        
        if (!$redis->set($test_key, $test_value)) {
            throw new Exception('Redis 写入测试失败');
        }
        
        $read_value = $redis->get($test_key);
        if ($read_value !== $test_value) {
            throw new Exception('Redis 读取测试失败');
        }
        
        // 清理测试数据
        $redis->del($test_key);
        $redis->close();

        echo '<div class="notice notice-success"><p>Redis 连接测试成功！</p></div>';
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>Redis 连接测试失败：' . esc_html($e->getMessage()) . '</p></div>';
    }
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

// 检查 Redis 连接状态
$redis_status = array(
    'extension_installed' => class_exists('Redis'),
    'connected' => false,
    'info' => array(),
    'error' => ''
);

if ($redis_status['extension_installed'] && $redis_enabled) {
    try {
        $redis = new Redis();
        $connected = $redis->connect($redis_host, $redis_port);
        
        if ($connected) {
            if ($redis_password) {
                $redis->auth($redis_password);
            }
            
            if ($redis_database) {
                $redis->select($redis_database);
            }
            
            $redis_status['connected'] = true;
            $redis_status['info'] = $redis->info();
            
            // 获取插件相关的缓存键数量
            $keys = $redis->keys('baby_device_manager_*');
            $redis_status['cache_keys'] = count($keys);
            
            $redis->close();
        } else {
            $redis_status['error'] = '无法连接到 Redis 服务器';
        }
    } catch (Exception $e) {
        $redis_status['error'] = $e->getMessage();
    }
}
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
        <h2>Redis 连接状态</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Redis 扩展</th>
                <td>
                    <?php if ($redis_status['extension_installed']): ?>
                        <span class="dashicons dashicons-yes" style="color: #46b450;"></span> 已安装
                    <?php else: ?>
                        <span class="dashicons dashicons-no" style="color: #dc3232;"></span> 未安装
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">连接状态</th>
                <td>
                    <?php if ($redis_enabled): ?>
                        <?php if ($redis_status['connected']): ?>
                            <span class="dashicons dashicons-yes" style="color: #46b450;"></span> 已连接
                        <?php else: ?>
                            <span class="dashicons dashicons-no" style="color: #dc3232;"></span> 未连接
                            <?php if ($redis_status['error']): ?>
                                <p class="description">错误信息：<?php echo esc_html($redis_status['error']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-marker" style="color: #ffb900;"></span> 已禁用
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($redis_status['connected']): ?>
                <tr>
                    <th scope="row">Redis 版本</th>
                    <td><?php echo esc_html($redis_status['info']['redis_version'] ?? '未知'); ?></td>
                </tr>
                <tr>
                    <th scope="row">运行时间</th>
                    <td><?php echo esc_html($redis_status['info']['uptime_in_days'] ?? '0'); ?> 天</td>
                </tr>
                <tr>
                    <th scope="row">内存使用</th>
                    <td><?php echo esc_html(round(($redis_status['info']['used_memory'] ?? 0) / 1024 / 1024, 2)); ?> MB</td>
                </tr>
                <tr>
                    <th scope="row">缓存键数量</th>
                    <td><?php echo esc_html($redis_status['cache_keys'] ?? 0); ?> 个</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="card">
        <h2>Redis 连接测试</h2>
        <form method="post" action="">
            <?php wp_nonce_field('test_redis'); ?>
            <p>点击下面的按钮测试 Redis 连接。</p>
            <p class="submit">
                <input type="submit" name="test_redis" class="button" value="测试连接">
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