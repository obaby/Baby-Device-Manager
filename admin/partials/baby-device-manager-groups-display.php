<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$groups_table = $wpdb->prefix . 'baby_device_groups';

// 处理表单提交
if (isset($_POST['submit_group'])) {
    if (check_admin_referer('save_group')) {
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $sort_order = intval($_POST['sort_order']);
        $is_hidden = isset($_POST['is_hidden']) ? 1 : 0;
        
        if (empty($name)) {
            echo '<div class="notice notice-error"><p>分组名称不能为空！</p></div>';
        } else {
            // 检查分组名称是否已存在
            $existing_group = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $groups_table WHERE name = %s AND id != %d",
                $name,
                isset($_POST['group_id']) ? intval($_POST['group_id']) : 0
            ));
            
            if ($existing_group) {
                echo '<div class="notice notice-error"><p>分组名称已存在！</p></div>';
            } else {
                if (isset($_POST['group_id'])) {
                    // 更新现有分组
                    $result = $wpdb->update(
                        $groups_table,
                        array(
                            'name' => $name,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'is_hidden' => $is_hidden
                        ),
                        array('id' => intval($_POST['group_id'])),
                        array('%s', '%s', '%d', '%d'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>分组已更新！</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>更新分组失败，请重试！</p></div>';
                    }
                } else {
                    // 添加新分组
                    $result = $wpdb->insert(
                        $groups_table,
                        array(
                            'name' => $name,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'is_hidden' => $is_hidden
                        ),
                        array('%s', '%s', '%d', '%d')
                    );
                    
                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>分组已添加！</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>添加分组失败，请重试！</p></div>';
                    }
                }
            }
        }
    }
}

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (check_admin_referer('delete_group')) {
        $group_id = intval($_GET['id']);
        
        // 检查分组下是否有设备
        $devices_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}baby_devices WHERE group_id = %d",
            $group_id
        ));
        
        if ($devices_count > 0) {
            echo '<div class="notice notice-error"><p>该分组下还有设备，无法删除！</p></div>';
        } else {
            $wpdb->delete($groups_table, array('id' => $group_id));
            echo '<div class="notice notice-success"><p>分组已删除！</p></div>';
        }
    }
}

// 获取要编辑的分组
$edit_group = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_group = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $groups_table WHERE id = %d",
        intval($_GET['id'])
    ));
}

// 获取所有分组
$groups = $wpdb->get_results("SELECT * FROM $groups_table ORDER BY sort_order ASC, name ASC");
?>

<div class="wrap">
    <h1>设备分组管理</h1>
    
    <div class="card">
        <h2><?php echo $edit_group ? '编辑分组' : '添加新分组'; ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('save_group'); ?>
            <?php if ($edit_group): ?>
                <input type="hidden" name="group_id" value="<?php echo esc_attr($edit_group->id); ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name">分组名称</label></th>
                    <td>
                        <input type="text" name="name" id="name" class="regular-text" 
                               value="<?php echo $edit_group ? esc_attr($edit_group->name) : ''; ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="description">分组描述</label></th>
                    <td>
                        <textarea name="description" id="description" class="large-text" rows="3"><?php echo $edit_group ? esc_textarea($edit_group->description) : ''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sort_order">排序</label></th>
                    <td>
                        <input type="number" name="sort_order" id="sort_order" class="small-text" 
                               value="<?php echo $edit_group ? esc_attr($edit_group->sort_order) : '0'; ?>">
                        <p class="description">数字越小越靠前</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_hidden">隐藏分组</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_hidden" id="is_hidden" value="1" 
                                   <?php echo ($edit_group && $edit_group->is_hidden) ? 'checked' : ''; ?>>
                            隐藏此分组（隐藏后在前台不会显示）
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit_group" class="button button-primary" 
                       value="<?php echo $edit_group ? '更新分组' : '添加分组'; ?>">
                <?php if ($edit_group): ?>
                    <a href="<?php echo admin_url('admin.php?page=baby-device-manager-groups'); ?>" class="button">取消</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    
    <div class="card">
        <h2>现有分组</h2>
        <?php if (empty($groups)): ?>
            <p>暂无分组</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>分组名称</th>
                        <th>描述</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><?php echo esc_html($group->sort_order); ?></td>
                            <td><?php echo esc_html($group->name); ?></td>
                            <td><?php echo esc_html($group->description); ?></td>
                            <td><?php echo $group->is_hidden ? '<span class="dashicons dashicons-hidden" title="已隐藏"></span>' : '<span class="dashicons dashicons-visibility" title="显示中"></span>'; ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=baby-device-manager-groups&action=edit&id=' . $group->id), 'edit_group'); ?>" 
                                   class="button button-small">编辑</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=baby-device-manager-groups&action=delete&id=' . $group->id), 'delete_group'); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('确定要删除这个分组吗？');">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div> 