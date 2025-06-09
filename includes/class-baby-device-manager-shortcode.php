<?php

class Baby_Device_Manager_Shortcode {
    public function __construct() {
        add_shortcode('baby_devices', array($this, 'render_devices'));
    }

    public function render_devices($atts) {
        // 获取设置值
        $default_per_row = get_option('bdm_devices_per_row', 3);
        
        $atts = shortcode_atts(array(
            'group' => '', // 按分组显示
            'status' => '', // 按状态显示
            'orderby' => 'sort_order', // 排序字段：sort_order（自定义排序）, created_at（创建时间）
            'order' => 'ASC', // 排序方向：ASC（升序）, DESC（降序）
            'per_row' => $default_per_row // 使用设置的值
        ), $atts);

        // 确保 per_row 是有效的数字
        $per_row = intval($atts['per_row']);
        if ($per_row < 1) $per_row = 3;
        if ($per_row > 6) $per_row = 6;

        global $wpdb;
        $groups_table = $wpdb->prefix . 'baby_device_groups';
        $devices_table = $wpdb->prefix . 'baby_devices';

        // 检查表是否存在
        if (!Baby_Device_Manager::check_tables()) {
            return '<div class="bdm-error">数据库表未正确创建，请重新激活插件。</div>';
        }

        // 检查是否有数据
        $groups_count = $wpdb->get_var("SELECT COUNT(*) FROM $groups_table");
        $devices_count = $wpdb->get_var("SELECT COUNT(*) FROM $devices_table");

        if ($groups_count == 0 || $devices_count == 0) {
            return '<div class="bdm-error">暂无数据，请先在管理后台添加设备分组和设备。</div>';
        }

        // 构建查询
        $sql = "SELECT d.*, g.name as group_name, g.sort_order as group_sort_order 
                FROM $devices_table d 
                INNER JOIN $groups_table g ON d.group_id = g.id 
                WHERE d.is_hidden = 0 AND g.is_hidden = 0";
        
        if (!empty($atts['group'])) {
            $sql .= $wpdb->prepare(" AND g.name = %s", $atts['group']);
        }
        
        if (!empty($atts['status'])) {
            $sql .= $wpdb->prepare(" AND d.status = %s", $atts['status']);
        }
        
        // 先按分组排序，再按设备排序
        $sql .= " ORDER BY g.sort_order ASC, g.name ASC, d." . esc_sql($atts['orderby']) . " " . esc_sql($atts['order']) . ", d.name ASC";
        
        $devices = $wpdb->get_results($sql);
        
        if (empty($devices)) {
            return '<div class="bdm-no-devices">暂无设备</div>';
        }

        // 按分组组织设备
        $grouped_devices = array();
        foreach ($devices as $device) {
            if (!empty($device->group_name)) {
                $grouped_devices[$device->group_name][] = $device;
            }
        }

        // 状态映射
        $status_map = array(
            '在售' => 'onsale',
            '停售' => 'stopped',
            '已售出' => 'sold',
            '维修中' => 'repairing',
            '已报废' => 'scrapped'
        );

        $output = '<div class="bdm-container">';
        foreach ($grouped_devices as $group_name => $group_devices) {
            $output .= '<h3 class="bdm-title">' . esc_html($group_name) . '</h3>';
            $output .= '<div class="bdm-device-list" style="--devices-per-row: ' . esc_attr($per_row) . ';">';
            foreach ($group_devices as $device) {
                $output .= '<div class="bdm-device-item">';
                if (!empty($device->image_url)) {
                    $output .= '<div class="bdm-device-cover">';
                    $output .= '<img class="bdm-device-image" src="' . esc_url($device->image_url) . '" alt="' . esc_attr($device->name) . '">';
                    $output .= '</div>';
                }
                $output .= '<div class="bdm-device-info">';
                $output .= '<div class="bdm-device-name">' . esc_html($device->name) . '</div>';
                if (!empty($device->description)) {
                    $output .= '<div class="bdm-device-description">' . esc_html($device->description) . '</div>';
                }
                $output .= '<div class="bdm-device-toolbar">';
                $status_class = isset($status_map[$device->status]) ? 'status-' . $status_map[$device->status] : 'status-default';
                if (!empty($device->product_url)) {
                    $output .= '<a href="' . esc_url($device->product_url) . '" target="_blank" rel="noopener">';
                    $output .= '<button class="bdm-button ' . esc_attr($status_class) . '">' . esc_html($device->status) . '</button>';
                    $output .= '</a>';
                } else {
                    $output .= '<button class="bdm-button ' . esc_attr($status_class) . '">' . esc_html($device->status) . '</button>';
                }
                $output .= '</div>';
                $output .= '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }
} 