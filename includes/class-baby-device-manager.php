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
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 创建设备分组表
        $groups_table = $wpdb->prefix . 'baby_device_groups';
        $sql = "CREATE TABLE IF NOT EXISTS $groups_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
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
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY group_id (group_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 保存当前版本号
        update_option('baby_device_manager_version', BABY_DEVICE_MANAGER_VERSION);
        
        // 设置默认选项
        if (!get_option('bdm_devices_per_row')) {
            update_option('bdm_devices_per_row', 3);
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