<?php
/**
 * Plugin Name: Baby Device Manager
 * Plugin URI: https://h4ck.org.cn
 * Description: 一个功能强大的WordPress设备管理系统插件，支持设备分组管理、设备信息管理、自定义排序、状态跟踪等功能。可以轻松管理各类设备，包括设备分组、设备状态、设备图片、产品链接等信息，并提供美观的前端展示界面。支持多种排序方式和筛选功能，是设备管理的理想解决方案。
 * Version: 1.0.4
 * Author: obaby
 * Author URI: https://h4ck.org.cn
 * Text Domain: baby-device-manager
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// 如果直接访问此文件，则退出
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('BABY_DEVICE_MANAGER_VERSION', '1.0.4');
define('BABY_DEVICE_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BABY_DEVICE_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载必要的文件
require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'includes/class-baby-device-manager.php';
require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'includes/class-baby-device-manager-shortcode.php';

// 激活插件时的钩子
register_activation_hook(__FILE__, array('Baby_Device_Manager', 'activate'));

// 停用插件时的钩子
register_deactivation_hook(__FILE__, array('Baby_Device_Manager', 'deactivate'));

// 升级插件时的钩子
add_action('plugins_loaded', 'baby_device_manager_check_version');
function baby_device_manager_check_version() {
    $current_version = get_option('baby_device_manager_version', '1.0.0');
    if (version_compare($current_version, BABY_DEVICE_MANAGER_VERSION, '<')) {
        // 执行升级操作
        baby_device_manager_upgrade($current_version);
        // 更新版本号
        update_option('baby_device_manager_version', BABY_DEVICE_MANAGER_VERSION);
    }
}

// 升级函数
function baby_device_manager_upgrade($old_version) {
    global $wpdb;
    
    // 从1.0.0升级到1.0.1
    if (version_compare($old_version, '1.0.1', '<')) {
        // 添加新的设置选项
        if (!get_option('bdm_devices_per_row')) {
            update_option('bdm_devices_per_row', 3);
        }
    }
    
    // 从1.0.1升级到1.0.2
    if (version_compare($old_version, '1.0.2', '<')) {
        // 更新数据库表结构
        $devices_table = $wpdb->prefix . 'baby_devices';
        $wpdb->query("ALTER TABLE $devices_table MODIFY COLUMN status varchar(50) NOT NULL DEFAULT '在售'");
    }
    
    // 从1.0.2升级到1.0.3
    if (version_compare($old_version, '1.0.3', '<')) {
        // 添加新的状态选项
        $devices_table = $wpdb->prefix . 'baby_devices';
        $wpdb->query("ALTER TABLE $devices_table MODIFY COLUMN status varchar(50) NOT NULL DEFAULT '在售'");
    }
}

// 初始化插件
function baby_device_manager_init() {
    $plugin = new Baby_Device_Manager();
    $plugin->run();
    
    // 初始化 shortcode
    new Baby_Device_Manager_Shortcode();
}
add_action('plugins_loaded', 'baby_device_manager_init');

// 加载前端样式
function baby_device_manager_enqueue_styles() {
    wp_enqueue_style(
        'baby-device-manager-public',
        BABY_DEVICE_MANAGER_PLUGIN_URL . 'assets/css/baby-device-manager-public.css',
        array(),
        BABY_DEVICE_MANAGER_VERSION
    );
}
add_action('wp_enqueue_scripts', 'baby_device_manager_enqueue_styles'); 