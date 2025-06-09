<?php
/**
 * Plugin Name: Baby Device Manager
 * Plugin URI: https://h4ck.org.cn
 * Description: 一个功能强大的WordPress设备管理系统插件，支持设备分组管理、设备信息管理、自定义排序、状态跟踪等功能。可以轻松管理各类设备，包括设备分组、设备状态、设备图片、产品链接等信息，并提供美观的前端展示界面。支持多种排序方式和筛选功能，是设备管理的理想解决方案。
 * Version: 1.0.8
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

// 防止重复加载
if (defined('BABY_DEVICE_MANAGER_LOADED')) {
    return;
}

// 检查插件目录名称是否正确
$plugin_dir = basename(dirname(__FILE__));
if (strtolower($plugin_dir) !== 'babydevicemanager') {
    error_log('Baby Device Manager: 插件目录名称必须为 babydevicemanager，当前目录: ' . $plugin_dir);
    return;
}

define('BABY_DEVICE_MANAGER_LOADED', true);

// 定义插件常量
define('BABY_DEVICE_MANAGER_VERSION', '1.0.8');
define('BABY_DEVICE_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BABY_DEVICE_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载主类文件
require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'includes/class-baby-device-manager.php';

// 激活、停用和卸载钩子
register_activation_hook(__FILE__, array('Baby_Device_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('Baby_Device_Manager', 'deactivate'));
register_uninstall_hook(__FILE__, array('Baby_Device_Manager', 'uninstall'));

// 升级函数
if (!function_exists('baby_device_manager_upgrade')) {
    function baby_device_manager_upgrade($old_version) {
        global $wpdb;
        
        try {
            // 确保数据库表存在
            if (!Baby_Device_Manager::check_tables()) {
                // 如果表不存在，重新创建
                Baby_Device_Manager::activate();
                return;
            }

            // 从1.0.0升级到1.0.1
            if (version_compare($old_version, '1.0.1', '<')) {
                if (!get_option('bdm_devices_per_row')) {
                    update_option('bdm_devices_per_row', 3);
                }
            }
            
            // 从1.0.1升级到1.0.2
            if (version_compare($old_version, '1.0.2', '<')) {
                $devices_table = $wpdb->prefix . 'baby_devices';
                $wpdb->query("ALTER TABLE $devices_table MODIFY COLUMN status varchar(50) NOT NULL DEFAULT '在售'");
            }

            // 从1.0.4升级到1.0.6
            if (version_compare($old_version, '1.0.6', '<')) {
                $groups_table = $wpdb->prefix . 'baby_device_groups';
                $devices_table = $wpdb->prefix . 'baby_devices';
                
                // 检查分组表的隐藏字段
                $groups_has_hidden = $wpdb->get_var("SHOW COLUMNS FROM $groups_table LIKE 'is_hidden'");
                if (!$groups_has_hidden) {
                    $wpdb->query("ALTER TABLE $groups_table ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
                }
                
                // 检查设备表的隐藏字段
                $devices_has_hidden = $wpdb->get_var("SHOW COLUMNS FROM $devices_table LIKE 'is_hidden'");
                if (!$devices_has_hidden) {
                    $wpdb->query("ALTER TABLE $devices_table ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
                }
            }

            // 更新版本号
            update_option('baby_device_manager_version', BABY_DEVICE_MANAGER_VERSION);
            
        } catch (Exception $e) {
            error_log('Baby Device Manager upgrade error: ' . $e->getMessage());
            throw $e;
        }
    }
}

// 手动触发升级
if (isset($_GET['page']) && $_GET['page'] === 'baby-device-manager' && isset($_GET['force_upgrade'])) {
    try {
        global $wpdb;
        
        // 检查表是否存在
        $devices_table = $wpdb->prefix . 'baby_devices';
        $groups_table = $wpdb->prefix . 'baby_device_groups';
        
        // 检查设备表的隐藏字段
        $devices_has_hidden = $wpdb->get_var("SHOW COLUMNS FROM $devices_table LIKE 'is_hidden'");
        if (!$devices_has_hidden) {
            $result = $wpdb->query("ALTER TABLE $devices_table ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
            if ($result === false) {
                throw new Exception('添加设备表隐藏字段失败: ' . $wpdb->last_error);
            }
        }
        
        // 检查分组表的隐藏字段
        $groups_has_hidden = $wpdb->get_var("SHOW COLUMNS FROM $groups_table LIKE 'is_hidden'");
        if (!$groups_has_hidden) {
            $result = $wpdb->query("ALTER TABLE $groups_table ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
            if ($result === false) {
                throw new Exception('添加分组表隐藏字段失败: ' . $wpdb->last_error);
            }
        }
        
        // 更新版本号
        update_option('baby_device_manager_version', BABY_DEVICE_MANAGER_VERSION);
        
        // 重定向到成功页面
        wp_redirect(admin_url('admin.php?page=baby-device-manager&upgraded=1'));
        exit;
        
    } catch (Exception $e) {
        // 记录错误
        error_log('Baby Device Manager upgrade error: ' . $e->getMessage());
        
        // 显示错误消息
        wp_die(
            '升级失败：' . esc_html($e->getMessage()),
            '升级错误',
            array('back_link' => true)
        );
    }
}

// 添加缺失的字段
function baby_device_manager_add_missing_columns() {
    global $wpdb;
    
    // 设备表
    $devices_table = $wpdb->prefix . 'baby_devices';
    $devices_has_hidden = $wpdb->get_var("SHOW COLUMNS FROM $devices_table LIKE 'is_hidden'");
    if (!$devices_has_hidden) {
        $wpdb->query("ALTER TABLE $devices_table ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
    }
    
    // 分组表
    $groups_table = $wpdb->prefix . 'baby_device_groups';
    $groups_has_hidden = $wpdb->get_var("SHOW COLUMNS FROM $groups_table LIKE 'is_hidden'");
    if (!$groups_has_hidden) {
        $wpdb->query("ALTER TABLE $groups_table ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
    }
}

// 初始化插件
if (!function_exists('baby_device_manager_init')) {
    function baby_device_manager_init() {
        try {
            // 加载其他依赖文件
            require_once BABY_DEVICE_MANAGER_PLUGIN_DIR . 'includes/class-baby-device-manager-shortcode.php';
            
            // 检查版本并升级
            $current_version = get_option('baby_device_manager_version', '1.0.0');
            if (version_compare($current_version, BABY_DEVICE_MANAGER_VERSION, '<')) {
                baby_device_manager_upgrade($current_version);
            }

            // 初始化主类
            $plugin = new Baby_Device_Manager();
            $plugin->run();
            
            // 初始化 shortcode
            new Baby_Device_Manager_Shortcode();
            
        } catch (Exception $e) {
            // 记录错误日志
            error_log('Baby Device Manager initialization error: ' . $e->getMessage());
            
            // 显示管理员通知
            if (is_admin()) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>Baby Device Manager 初始化失败: ' . esc_html($e->getMessage()) . '</p></div>';
                });
            }
        }
    }
}

// 在插件加载时初始化
add_action('plugins_loaded', 'baby_device_manager_init');
add_action('admin_init', 'baby_device_manager_add_missing_columns');

// 加载前端样式
if (!function_exists('baby_device_manager_enqueue_styles')) {
    function baby_device_manager_enqueue_styles() {
        wp_enqueue_style(
            'baby-device-manager-public',
            BABY_DEVICE_MANAGER_PLUGIN_URL . 'assets/css/baby-device-manager-public.css',
            array(),
            BABY_DEVICE_MANAGER_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'baby_device_manager_enqueue_styles'); 