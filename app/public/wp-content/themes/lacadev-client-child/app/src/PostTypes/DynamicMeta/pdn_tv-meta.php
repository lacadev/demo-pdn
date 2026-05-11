<?php

/**
 * Meta fields cho CPT: pdn_tv
 * File được sinh tự động — có thể chỉnh sửa trực tiếp.
 * Thay đổi có hiệu lực ngay sau khi lưu (không cần compile).
 *
 * Tham khảo Carbon Fields API: https://docs.carbonfields.net
 */

add_action('carbon_fields_register_fields', function () {
    \Carbon_Fields\Container\Container::make('post_meta', __('Thông tin PĐN TV', 'laca'))
        ->where('post_type', '=', 'pdn_tv')
        ->add_fields([
            \Carbon_Fields\Field\Field::make('text', 'ytb_url', __('Youtube url', 'laca')),
        ]);
});