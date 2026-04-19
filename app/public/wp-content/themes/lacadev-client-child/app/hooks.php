<?php
/**
 * Child Theme Hooks
 *
 * Đăng ký các actions và filters riêng của child theme.
 * Parent theme hooks vẫn chạy bình thường — chỉ thêm/ghi đè ở đây.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CHILD HOOKS — thêm hooks của bạn bên dưới
// =============================================================================

// ── Gallery Archive AJAX Handler ─────────────────────────────────────────────
require_once CHILD_APP_DIR . 'src/Ajax/GalleryAjaxHandler.php';

// Ví dụ: ghi đè excerpt length của parent
// add_filter('excerpt_length', function($length) {
//     return 25;
// }, 1000);

// Ví dụ: thêm custom body class
// add_filter('body_class', function($classes) {
//     $classes[] = 'child-theme';
//     return $classes;
// });
