jQuery(document).ready(function($) {
    // 删除确认
    $('.delete-device, .delete-group').on('click', function(e) {
        if (!confirm('确定要删除这个项目吗？')) {
            e.preventDefault();
        }
    });

    // 图片预览
    $('#device_image').on('change', function() {
        var url = $(this).val();
        if (url) {
            var preview = $('<img>', {
                src: url,
                style: 'max-width: 200px; margin-top: 10px;'
            });
            $(this).after(preview);
        }
    });

    // 表单验证
    $('form').on('submit', function(e) {
        var required = $(this).find('[required]');
        var valid = true;

        required.each(function() {
            if (!$(this).val()) {
                valid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('请填写所有必填字段');
        }
    });

    // 添加错误样式
    $('<style>.error { border-color: #dc3232 !important; }</style>').appendTo('head');
}); 