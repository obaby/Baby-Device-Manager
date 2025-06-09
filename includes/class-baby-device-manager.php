<?php

class Baby_Device_Manager {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'baby-device-manager';
        $this->version = BABY_DEVICE_MANAGER_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    private function load_dependencies() {
        require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'includes/class-baby-device-manager-loader.php';
        require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'admin/class-baby-device-manager-admin.php';
        $this->loader = new Baby_Device_Manager_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new Baby_Device_Manager_Admin($this->get_plugin_name(), $this->get_version());
        
        // 添加管理菜单
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        
        // 注册设备分组和设备自定义文章类型
        $this->loader->add_action('init', $plugin_admin, 'register_post_types');
        
        // 添加管理页面样式和脚本
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }

    public static function activate() {
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            // 创建设备分组表
            $groups_table = $wpdb->prefix . 'baby_device_groups';
            $sql = "CREATE TABLE IF NOT EXISTS $groups_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                description text,
                is_hidden tinyint(1) NOT NULL DEFAULT 0,
                sort_order int(11) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY name (name)
            ) $charset_collate;";

            // 创建设备表
            $devices_table = $wpdb->prefix . 'baby_devices';
            $sql .= "CREATE TABLE IF NOT EXISTS $devices_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                group_id bigint(20) NOT NULL,
                description text,
                status varchar(50) NOT NULL DEFAULT '在售',
                image_url text,
                product_url text,
                is_hidden tinyint(1) NOT NULL DEFAULT 0,
                sort_order int(11) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY group_id (group_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);

            if (is_wp_error($result)) {
                throw new Exception('Failed to create database tables: ' . $result->get_error_message());
            }

            // 保存当前版本号
            update_option('baby_device_manager_version', BABY_DEVICE_MANAGER_VERSION);
            
            // 设置默认选项
            if (!get_option('bdm_devices_per_row')) {
                update_option('bdm_devices_per_row', 3);
            }

            // 清空缓存
            self::clear_cache();

            // 刷新重写规则
            flush_rewrite_rules();

            // 记录成功日志
            error_log('Baby Device Manager activated successfully');
            
        } catch (Exception $e) {
            // 记录错误日志
            error_log('Baby Device Manager activation error: ' . $e->getMessage());
            
            // 如果激活失败，尝试清理
            self::deactivate();
            
            // 抛出异常，让WordPress知道激活失败
            throw new Exception('Failed to activate Baby Device Manager: ' . $e->getMessage());
        }
    }

    /**
     * 清空缓存
     */
    private static function clear_cache() {
        try {
            // 清空Redis缓存中的插件相关数据
            if (class_exists('Redis')) {
                try {
                    $redis = new Redis();
                    // 尝试连接Redis服务器
                    if ($redis->connect('127.0.0.1', 6379)) {
                        // 只删除插件相关的缓存键
                        $keys = $redis->keys('baby_device_manager_*');
                        if (!empty($keys)) {
                            $redis->del($keys);
                        }
                        // 关闭连接
                        $redis->close();
                    }
                } catch (Exception $e) {
                    // 记录错误日志
                    error_log('Baby Device Manager Redis cache clear error: ' . $e->getMessage());
                }
            }

            // 清空WordPress对象缓存中的插件数据
            try {
                wp_cache_delete('baby_device_manager_groups', 'options');
                wp_cache_delete('baby_device_manager_devices', 'options');
                wp_cache_delete('baby_device_manager_settings', 'options');
            } catch (Exception $e) {
                error_log('Baby Device Manager WordPress object cache clear error: ' . $e->getMessage());
            }
            
            // 清空WordPress瞬态缓存中的插件数据
            try {
                global $wpdb;
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_baby_device_manager_%'");
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_site_transient_baby_device_manager_%'");
            } catch (Exception $e) {
                error_log('Baby Device Manager WordPress transient cache clear error: ' . $e->getMessage());
            }
            
            // 清空页面缓存中的插件页面
            try {
                if (function_exists('w3tc_flush_post')) {
                    // 获取所有设备页面
                    $devices = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_type = 'baby_device'");
                    if ($devices) {
                        foreach ($devices as $device) {
                            w3tc_flush_post($device->ID);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Baby Device Manager W3TC cache clear error: ' . $e->getMessage());
            }

            // 清空其他缓存插件
            try {
                if (function_exists('wp_cache_clear_cache')) {
                    wp_cache_clear_cache();
                }
                if (function_exists('wpfc_clear_all_cache')) {
                    wpfc_clear_all_cache();
                }
                if (function_exists('wpe_cache_flush')) {
                    wpe_cache_flush();
                }
                if (function_exists('rocket_clean_domain')) {
                    rocket_clean_domain();
                }
                if (function_exists('autoptimize_cache_clear')) {
                    autoptimize_cache_clear();
                }
                if (function_exists('sg_cachepress_purge_cache')) {
                    sg_cachepress_purge_cache();
                }
                if (function_exists('litespeed_purge_all')) {
                    litespeed_purge_all();
                }
            } catch (Exception $e) {
                error_log('Baby Device Manager other cache plugins clear error: ' . $e->getMessage());
            }

            // 记录成功日志
            error_log('Baby Device Manager cache cleared successfully');
            
        } catch (Exception $e) {
            // 记录总体错误日志
            error_log('Baby Device Manager cache clear error: ' . $e->getMessage());
        }
    }

    public static function deactivate() {
        // 停用时不做任何操作，保留所有数据
        // 只记录停用状态
        update_option('baby_device_manager_deactivated', true);
    }

    public static function uninstall() {
        // 只有在用户选择卸载插件时才删除数据
        if (get_option('baby_device_manager_uninstall_data', false)) {
            global $wpdb;
            
            // 删除数据表
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}baby_devices");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}baby_device_groups");
            
            // 删除选项
            delete_option('baby_device_manager_version');
            delete_option('baby_device_manager_deactivated');
            delete_option('bdm_devices_per_row');
            delete_option('baby_device_manager_uninstall_data');

            // 删除菜单项
            remove_menu_page('baby-device-manager');
            remove_submenu_page('baby-device-manager', 'baby-device-manager');
            remove_submenu_page('baby-device-manager', 'baby-device-manager-groups');
            remove_submenu_page('baby-device-manager', 'baby-device-manager-add-device');
            remove_submenu_page('baby-device-manager', 'baby-device-manager-settings');

            // 删除自定义文章类型
            unregister_post_type('baby_device');

            // 清理缓存
            self::clear_cache();

            // 刷新重写规则
            flush_rewrite_rules();
        }
    }

    public static function check_tables() {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'baby_device_groups';
        $devices_table = $wpdb->prefix . 'baby_devices';
        
        // 检查表是否存在
        $groups_exists = $wpdb->get_var("SHOW TABLES LIKE '$groups_table'") === $groups_table;
        $devices_exists = $wpdb->get_var("SHOW TABLES LIKE '$devices_table'") === $devices_table;
        
        // 如果表不存在，尝试重新创建
        if (!$groups_exists || !$devices_exists) {
            self::activate();
            return false;
        }
        
        return true;
    }
} 