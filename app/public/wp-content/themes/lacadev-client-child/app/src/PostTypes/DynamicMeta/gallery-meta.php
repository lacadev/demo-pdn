<?php

/**
 * Meta fields cho CPT: gallery
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('Thông tin gallery', 'laca'))
        ->where('post_type', '=', 'gallery')
        ->add_fields([
            \Carbon_Fields\Field\Field::make('text', 'investor', __('Chủ đầu tư', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập tên chủ đầu tư'),
                \Carbon_Fields\Field\Field::make('text', 'floors', __('Số tầng', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập tên chủ đầu tư'),
                \Carbon_Fields\Field\Field::make('text', 'location', __('Địa điểm', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập địa điểm'),

                \Carbon_Fields\Field\Field::make('text', 'total_area', __('Tổng diện tích', 'laca'))
                    ->set_width(25)
                    ->set_attribute('placeholder', 'Nhập tổng diện tích'),

                \Carbon_Fields\Field\Field::make('media_gallery', 'project_gallery', __('Thư viện ảnh dự án', 'laca')),
        ]);
});