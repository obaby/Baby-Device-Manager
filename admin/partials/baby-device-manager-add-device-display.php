<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// 处理表单提交
if (isset($_POST['submit_device'])) {
    if (check_admin_referer('add_device')) {
        $name = sanitize_text_field($_POST['device_name']);
        $group_id = intval($_POST['device_group']);
        $description = sanitize_textarea_field($_POST['device_description']);
        $status = sanitize_text_field($_POST['device_status']);
        $image_url = esc_url_raw($_POST['device_image_url']);
        $product_url = esc_url_raw($_POST['device_product_url']);
        $sort_order = intval($_POST['device_sort_order']);

        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            // 更新设备
            $wpdb->update(
                $wpdb->prefix . 'baby_devices',
                array(
                    'name' => $name,
                    'group_id' => $group_id,
                    'description' => $description,
                    'status' => $status,
                    'image_url' => $image_url,
                    'product_url' => $product_url,
                    'sort_order' => $sort_order
                ),
                array('id' => intval($_GET['id'])),
                array('%s', '%d', '%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            echo '<div class="notice notice-success"><p>设备更新成功！</p></div>';
        } else {
            // 添加新设备
            $wpdb->insert(
                $wpdb->prefix . 'baby_devices',
                array(
                    'name' => $name,
                    'group_id' => $group_id,
                    'description' => $description,
                    'status' => $status,
                    'image_url' => $image_url,
                    'product_url' => $product_url,
                    'sort_order' => $sort_order
                ),
                array('%s', '%d', '%s', '%s', '%s', '%s', '%d')
            );
            echo '<div class="notice notice-success"><p>设备添加成功！</p></div>';
        }
    }
}

// 获取设备分组
$groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baby_device_groups ORDER BY sort_order ASC, name ASC");

// 如果是编辑模式，获取设备信息
$device = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $device = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}baby_devices WHERE id = %d",
        intval($_GET['id'])
    ));
}
?>

<div class="wrap">
    <h1><?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? '编辑设备' : '添加设备'; ?></h1>
    
    <div class="card">
        <form method="post" action="">
            <?php wp_nonce_field('add_device'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="device_name">设备名称</label></th>
                    <td>
                        <input type="text" name="device_name" id="device_name" class="regular-text" required
                               value="<?php echo $device ? esc_attr($device->name) : ''; ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="device_group">所属分组</label></th>
                    <td>
                        <select name="device_group" id="device_group" required>
                            <option value="">请选择分组</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group->id; ?>" <?php selected($device && $device->group_id == $group->id); ?>>
                                    <?php echo esc_html($group->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="device_description">设备描述</label></th>
                    <td>
                        <textarea name="device_description" id="device_description" class="large-text" rows="3"><?php echo $device ? esc_textarea($device->description) : ''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="device_status">设备状态</label></th>
                    <td>
                        <select name="device_status" id="device_status" required>
                            <option value="在售" <?php selected($device && $device->status == '在售'); ?>>在售</option>
                            <option value="停售" <?php selected($device && $device->status == '停售'); ?>>停售</option>
                            <option value="已售出" <?php selected($device && $device->status == '已售出'); ?>>已售出</option>
                            <option value="维修中" <?php selected($device && $device->status == '维修中'); ?>>维修中</option>
                            <option value="已报废" <?php selected($device && $device->status == '已报废'); ?>>已报废</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="device_image_url">设备图片URL</label></th>
                    <td>
                        <input type="url" name="device_image_url" id="device_image_url" class="regular-text"
                               value="<?php echo $device ? esc_url($device->image_url) : ''; ?>"
                               onchange="updateImagePreview(this.value)">
                        <p class="description">输入设备图片的URL地址</p>
                        <div id="image_preview" style="margin-top: 10px; max-width: 300px;">
                            <?php if ($device && $device->image_url): ?>
                                <img src="<?php echo esc_url($device->image_url); ?>" alt="设备图片预览" style="max-width: 100%;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="device_product_url">产品链接</label></th>
                    <td>
                        <input type="url" name="device_product_url" id="device_product_url" class="regular-text"
                               value="<?php echo $device ? esc_url($device->product_url) : ''; ?>">
                        <p class="description">输入产品详情页的URL地址</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="device_sort_order">排序</label></th>
                    <td>
                        <input type="number" name="device_sort_order" id="device_sort_order" class="small-text" value="<?php echo $device ? esc_attr($device->sort_order) : '0'; ?>">
                        <p class="description">数字越小越靠前显示，默认为0</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit_device" class="button button-primary" value="<?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? '更新设备' : '添加设备'; ?>">
                <a href="<?php echo admin_url('admin.php?page=baby-device-manager'); ?>" class="button">返回列表</a>
            </p>
        </form>
    </div>
</div>

<script>
function updateImagePreview(url) {
    var preview = document.getElementById('image_preview');
    if (url) {
        preview.innerHTML = '<img src="' + url + '" alt="设备图片预览" style="max-width: 100%;">';
    } else {
        preview.innerHTML = '';
    }
}
</script> 