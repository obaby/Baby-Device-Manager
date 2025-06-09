<?php

class Baby_Device_Manager_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // 添加处理删除设备的动作
        add_action('admin_init', array($this, 'handle_device_actions'));
    }

    public function handle_device_actions() {
        if (isset($_GET['page']) && $_GET['page'] === 'baby-device-manager' && 
            isset($_GET['action']) && $_GET['action'] === 'delete' && 
            isset($_GET['id']) && isset($_GET['_wpnonce'])) {
            
            $device_id = intval($_GET['id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_device_' . $device_id)) {
                global $wpdb;
                $result = $wpdb->delete(
                    $wpdb->prefix . 'baby_devices',
                    array('id' => $device_id),
                    array('%d')
                );
                
                if ($result !== false) {
                    wp_redirect(admin_url('admin.php?page=baby-device-manager&message=deleted'));
                    exit;
                } else {
                    wp_redirect(admin_url('admin.php?page=baby-device-manager&error=delete_failed'));
                    exit;
                }
            }
        }
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, BABY_DEVICE_MANAGER_PLUGIN_URL . 'assets/css/baby-device-manager-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, BABY_DEVICE_MANAGER_PLUGIN_URL . 'assets/js/baby-device-manager-admin.js', array('jquery'), $this->version, false);
    }

    public function add_plugin_admin_menu() {
        // 添加主菜单
        add_menu_page(
            '设备管理',
            '设备管理',
            'manage_options',
            'baby-device-manager',
            array($this, 'display_plugin_admin_page'),
            'dashicons-smartphone',
            30
        );

        // 添加子菜单
        add_submenu_page(
            'baby-device-manager',
            '设备列表',
            '设备列表',
            'manage_options',
            'baby-device-manager',
            array($this, 'display_plugin_admin_page')
        );

        add_submenu_page(
            'baby-device-manager',
            '添加设备',
            '添加设备',
            'manage_options',
            'baby-device-manager-add-device',
            array($this, 'display_plugin_add_device_page')
        );

        add_submenu_page(
            'baby-device-manager',
            '设备分组',
            '设备分组',
            'manage_options',
            'baby-device-manager-groups',
            array($this, 'display_plugin_groups_page')
        );

        add_submenu_page(
            'baby-device-manager',
            '设置',
            '设置',
            'manage_options',
            'baby-device-manager-settings',
            array($this, 'display_plugin_settings_page')
        );
    }

    public function display_plugin_admin_page() {
        require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'admin/partials/baby-device-manager-admin-display.php';
    }

    public function display_plugin_groups_page() {
        require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'admin/partials/baby-device-manager-groups-display.php';
    }

    public function display_plugin_add_device_page() {
        require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'admin/partials/baby-device-manager-add-device-display.php';
    }

    public function display_plugin_settings_page() {
        require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'admin/partials/baby-device-manager-settings-display.php';
    }

    public function register_post_types() {
        // 注册设备分组分类法
        register_taxonomy(
            'device_group',
            'device',
            array(
                'hierarchical' => true,
                'label' => '设备分组',
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'device-group'),
            )
        );

        // 注册设备自定义文章类型
        register_post_type(
            'device',
            array(
                'labels' => array(
                    'name' => '设备',
                    'singular_name' => '设备',
                    'add_new' => '添加设备',
                    'add_new_item' => '添加新设备',
                    'edit_item' => '编辑设备',
                    'new_item' => '新设备',
                    'view_item' => '查看设备',
                    'search_items' => '搜索设备',
                    'not_found' => '未找到设备',
                    'not_found_in_trash' => '回收站中未找到设备',
                ),
                'public' => true,
                'has_archive' => true,
                'supports' => array('title', 'editor', 'thumbnail'),
                'menu_icon' => 'dashicons-admin-generic',
                'show_in_menu' => false,
            )
        );
    }
} 