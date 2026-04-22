<?php
/**
 * Child Theme Options
 *
 * Add custom fields or tabs to the main theme options page.
 *
 * @package LacaDevClientChild
 */

use Carbon_Fields\Field\Field;

add_action('lacadev/theme_options/register_child_tabs', function ($optionsPage) {
    if (!$optionsPage) {
        return;
    }

    $optionsPage->add_tab(__('Tuỳ chỉnh giao diện (Child)', 'laca'), [
        Field::make('textarea', 'footer_contact_budget_options', __('Ngân sách form liên hệ footer', 'laca'))
        ->set_width(50)
            ->set_help_text(__('Mỗi dòng là một lựa chọn ngân sách.', 'laca'))
            ->set_default_value("Dưới 1 tỷ\n1 - 3 tỷ\n3 - 5 tỷ\n5 - 10 tỷ\nTrên 10 tỷ"),
        Field::make('image', 'footer_contact_image', __('Hình ảnh form liên hệ footer', 'laca'))
        ->set_width(50)
            ->set_help_text(__('Ảnh hiển thị bên phải form liên hệ ở footer.', 'laca')),
    ]);
});
