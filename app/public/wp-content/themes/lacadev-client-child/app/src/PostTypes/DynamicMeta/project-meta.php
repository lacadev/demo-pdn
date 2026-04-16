<?php

/**
 * Meta fields cho CPT: project
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('Thong tin Du an', 'laca'))
        ->where('post_type', '=', 'project')
        ->add_fields([
            \Carbon_Fields\Field\Field::make('text', 'investor', __('Chu dau tu', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Nhap ten chu dau tu'),
            \Carbon_Fields\Field\Field::make('text', 'floors', __('So tang', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Nhap so tang'),
            \Carbon_Fields\Field\Field::make('text', 'location', __('Dia diem', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Nhap dia diem'),

            \Carbon_Fields\Field\Field::make('text', 'total_area', __('Tong dien tich', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Nhap tong dien tich'),
            \Carbon_Fields\Field\Field::make('text', 'house_area', __('Dien tich nha', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Nhap dien tich nha'),
            \Carbon_Fields\Field\Field::make('text', 'front_area', __('Mat tien', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Nhap mat tien'),

            \Carbon_Fields\Field\Field::make('text', 'execution_year', __('Nam thuc hien', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'Nhap nam thuc hien'),
            \Carbon_Fields\Field\Field::make('text', 'design_type', __('Loai thiet ke', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'Nhap loai thiet ke'),
            \Carbon_Fields\Field\Field::make('rich_text', 'usage_function', __('Cong nang su dung', 'laca'))
                ->set_attribute('placeholder', 'Nhap cong nang su dung'),

            \Carbon_Fields\Field\Field::make('complex', 'project_gallery', __('Thu vien anh du an', 'laca'))
                ->set_layout('tabbed-horizontal')
                ->set_collapsed(true)
                ->add_fields([
                    \Carbon_Fields\Field\Field::make('image', 'gallery_image', __('Anh', 'laca'))
                        ->set_value_type('id'),
                ]),
        ]);
});