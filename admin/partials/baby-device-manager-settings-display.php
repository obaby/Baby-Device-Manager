<?php
if (!defined('ABSPATH')) {
    exit;
}

// 处理表单提交
if (isset($_POST['submit_settings'])) {
    if (check_admin_referer('save_settings')) {
        $devices_per_row = intval($_POST['devices_per_row']);
        if ($devices_per_row < 1) $devices_per_row = 3;
        if ($devices_per_row > 6) $devices_per_row = 6;
        
        update_option('bdm_devices_per_row', $devices_per_row);
        echo '<div class="notice notice-success"><p>设置已保存！</p></div>';
    }
}

// 获取当前设置
$devices_per_row = get_option('bdm_devices_per_row', 3);
?>

<div class="wrap">
    <h1>设备管理设置</h1>
    
    <div class="card">
        <form method="post" action="">
            <?php wp_nonce_field('save_settings'); ?>
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
            </table>
            <p class="submit">
                <input type="submit" name="submit_settings" class="button button-primary" value="保存设置">
            </p>
        </form>
    </div>
</div> 