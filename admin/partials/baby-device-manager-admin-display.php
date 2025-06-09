<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>设备管理</h1>
    
    <?php
    // 显示操作消息
    if (isset($_GET['message']) && $_GET['message'] === 'deleted') {
        echo '<div class="notice notice-success is-dismissible"><p>设备已成功删除。</p></div>';
    }
    if (isset($_GET['error']) && $_GET['error'] === 'delete_failed') {
        echo '<div class="notice notice-error is-dismissible"><p>删除设备失败，请重试。</p></div>';
    }
    ?>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=baby-device-manager-add-device'); ?>" class="button button-primary">添加设备</a>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>设备名称</th>
                <th>分组</th>
                <th>状态</th>
                <th>描述</th>
                <th>显示状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            $devices = $wpdb->get_results("
                SELECT d.*, g.name as group_name 
                FROM {$wpdb->prefix}baby_devices d 
                LEFT JOIN {$wpdb->prefix}baby_device_groups g ON d.group_id = g.id 
                ORDER BY d.created_at DESC
            ");

            if ($devices) {
                foreach ($devices as $device) {
                    ?>
                    <tr>
                        <td><?php echo esc_html($device->name); ?></td>
                        <td><?php echo esc_html($device->group_name); ?></td>
                        <td><?php echo esc_html($device->status); ?></td>
                        <td><?php echo esc_html($device->description); ?></td>
                        <td><?php echo $device->is_hidden ? '<span class="dashicons dashicons-hidden" title="已隐藏"></span>' : '<span class="dashicons dashicons-visibility" title="显示中"></span>'; ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=baby-device-manager-add-device&action=edit&id=' . $device->id); ?>" class="button button-small">编辑</a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=baby-device-manager&action=delete&id=' . $device->id), 'delete_device_' . $device->id); ?>" class="button button-small" onclick="return confirm('确定要删除这个设备吗？');">删除</a>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="6">暂无设备</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div> 