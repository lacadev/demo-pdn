<?php
/**
 * Asset helpers.
 *
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Theme;
use WPEmergeTheme\Facades\Assets;

/**
 * Enhanced asset loading with performance optimizations
 */
function app_action_theme_enqueue_assets()
{
    $version = wp_get_theme()->get('Version');
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';

    /**
     * Enqueue the built-in comment-reply script for singular pages.
     */
    if (is_singular()) {
        wp_enqueue_script('comment-reply');
    }

    /**
     * Critical JS (inline or very small) - load in head for critical functionality
     */
    if (file_exists($dist_path . 'critical.js')) {
        wp_enqueue_script('theme-critical-js', $dist_url . 'critical.js', [], $version, false);
    }

    /**
     * Vendors bundle
     */
    $vendors_deps = [];
    if (file_exists($dist_path . 'vendors.js')) {
        wp_enqueue_script('theme-vendors-js', $dist_url . 'vendors.js', [], $version, true);
        $vendors_deps = ['theme-vendors-js'];
    }

    /**
     * Main JavaScript bundle (deferred)
     */
    Assets::enqueueScript('theme-js-bundle', $dist_url . 'theme.js', $vendors_deps, true);

    /**
     * Conditional assets based on page type
     */
    if (is_home() || is_archive() || is_search()) {
        if (file_exists($dist_path . 'archive.js')) {
            wp_enqueue_script('theme-archive-js', $dist_url . 'archive.js', ['theme-js-bundle'], $version, true);
        }
    }

    if (is_single() && comments_open()) {
        if (file_exists($dist_path . 'comments.js')) {
            wp_enqueue_script('theme-comments-js', $dist_url . 'comments.js', ['theme-js-bundle'], $version, true);
        }
    }

    /**
     * Enqueue styles with preload optimization
     */
    Assets::enqueueStyle('theme-css-bundle', $dist_url . 'styles/theme.css');

    /**
     * Conditional CSS based on page type
     */
    if (is_single()) {
        if (file_exists($dist_path . 'styles/single.css')) {
            wp_enqueue_style('theme-single-css', $dist_url . 'styles/single.css', ['theme-css-bundle'], $version);
        }
    }

    /**
     * Enqueue theme's style.css file to allow overrides for the bundled styles.
     */
    Assets::enqueueStyle('theme-styles', get_template_directory_uri() . '/style.css');

    /**
     * Localize script with minimal data
     */
    wp_localize_script('theme-js-bundle', 'themeData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_nonce'),
        'isHome' => is_home(),
        'isMobile' => wp_is_mobile(),
        'currentUrl' => get_permalink(),
    ]);
}

/**
 * Enqueue admin assets.
 *
 * @return void
 */
function app_action_admin_enqueue_assets()
{
    // dist/ nằm ở .../lacadev-client/dist/ nên cần dirname() để lên 1 level
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        'theme-admin-css-bundle',
        $template_dir . '/dist/styles/admin.css'
    );
    Assets::enqueueStyle(
        'theme-editor-css-bundle',
        $template_dir . '/dist/styles/editor.css'
    );

    /**
     * Enqueue vendors.js if exists (same fix as frontend)
     * CRITICAL: Load in head (false) to ensure it's available before admin.js
     */
    $admin_deps = [];
    $theme_root = dirname(get_stylesheet_directory());
    $vendors_path = $theme_root . '/dist/vendors.js';
    
    if (file_exists($vendors_path)) {
        $base_uri = get_stylesheet_directory_uri();
        $theme_uri = dirname($base_uri);
        $vendors_url = $theme_uri . '/dist/vendors.js';
        
        // Load in <head> without defer to ensure Swal is available
        wp_enqueue_script('theme-vendors-js', $vendors_url, [], wp_get_theme()->get('Version'), false);
        $admin_deps = ['theme-vendors-js'];
    }

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-admin-js-bundle',
        $template_dir . '/dist/admin.js',
        $admin_deps,
        true
    );

    /**
     * Localize admin script data with nonce for AJAX requests and i18n strings
     */
    wp_localize_script('theme-admin-js-bundle', 'ajaxurl_params', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('update_post_thumbnail'),  // Must match backend check_ajax_referer
    ]);

    /**
     * Localize i18n strings for admin JavaScript
     */
    wp_localize_script('theme-admin-js-bundle', 'adminI18n', [
        // Thumbnail removal
        'removeThumbnailTitle' => __('Remove Thumbnail?', 'lacadev'),
        'removeThumbnailText' => __('Are you sure you want to remove this featured image?', 'lacadev'),
        'removeThumbnailConfirm' => __('Yes, remove it', 'lacadev'),
        'removeThumbnailCancel' => __('Cancel', 'lacadev'),
        'removedTitle' => __('Removed!', 'lacadev'),
        'removedText' => __('Featured image has been removed.', 'lacadev'),
        'errorTitle' => __('Error!', 'lacadev'),
        'failedRemove' => __('Failed to remove thumbnail.', 'lacadev'),
        
        // UI labels
        'chooseImage' => __('Choose image', 'lacadev'),
        'setFeaturedImage' => __('Set featured image', 'lacadev'),
    ]);

    /**
     * Localize project chart data — chỉ inject trên trang Dashboard (index.php).
     * Dữ liệu được đọc từ custom post type 'project' nếu đã đăng ký.
     */
    $current_screen = get_current_screen();
    if ($current_screen && $current_screen->id === 'dashboard' && post_type_exists('project')) {
        global $wpdb;

        // byStatus: đếm project theo meta _project_status (Carbon Fields)
        $status_labels = [
            'pending'     => '🕐 Chờ làm',
            'in_progress' => '🔨 Đang làm',
            'done'        => '✅ Đã xong',
            'maintenance' => '🔧 Đang bảo trì',
            'paused'      => '⏸️ Tạm dừng',
        ];

        $status_rows = $wpdb->get_results("
            SELECT
                COALESCE(pm.meta_value, 'pending') AS `key`,
                COUNT(*) AS `count`
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id AND pm.meta_key = '_project_status'
            WHERE p.post_type = 'project'
              AND p.post_status NOT IN ('trash','auto-draft','inherit')
            GROUP BY `key`
        ");

        $by_status = [];
        foreach ($status_rows as $row) {
            $by_status[] = [
                'key'   => $row->key,
                'label' => $status_labels[$row->key] ?? ucfirst($row->key),
                'count' => (int) $row->count,
            ];
        }

        // byMonth: đếm project tạo mới trong 12 tháng gần nhất
        $month_rows = $wpdb->get_results("
            SELECT
                DATE_FORMAT(post_date, '%Y-%m') AS ym,
                COUNT(*) AS cnt
            FROM {$wpdb->posts}
            WHERE post_type = 'project'
              AND post_status NOT IN ('trash','auto-draft','inherit')
              AND post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY ym
            ORDER BY ym ASC
        ");

        // Lấp đầy các tháng còn thiếu
        $month_map = [];
        foreach ($month_rows as $r) {
            $month_map[$r->ym] = (int) $r->cnt;
        }
        $by_month = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym    = date('Y-m', strtotime("-{$i} months"));
            $label = 'T' . (int) date('n', strtotime("-{$i} months"));
            $by_month[] = [
                'month' => $label,
                'count' => $month_map[$ym] ?? 0,
            ];
        }

        wp_localize_script('theme-admin-js-bundle', 'lacaProjectCharts', [
            'primary'  => carbon_get_theme_option('primary_color_ad') ?: '#2ea2cc',
            'byStatus' => $by_status,
            'byMonth'  => $by_month,
        ]);
    }

    // Enqueue front-end styles in admin area
    //  Assets::enqueueStyle('theme-css-bundle', $template_dir . '/dist/styles/theme.css');

    // Inject dynamic admin colors as CSS variables
    $primary_color_ad = carbon_get_theme_option('primary_color_ad') ?: '#2ea2cc';
    $secondary_color_ad = carbon_get_theme_option('secondary_color_ad') ?: '#1d2327';
    $bg_color_ad = carbon_get_theme_option('bg_color_ad') ?: '#f0f0f1';
    $text_color_ad = carbon_get_theme_option('text_color_ad') ?: '#3c434a';

    $custom_css = "
        :root {
            --primary-color-ad: {$primary_color_ad};
            --secondary-color-ad: {$secondary_color_ad};
            --bg-color-ad: {$bg_color_ad};
            --text-color-ad: {$text_color_ad};
        }
    ";
    wp_add_inline_style('theme-admin-css-bundle', $custom_css);
}

/**
 * Preload critical assets in admin_head
 */
add_action('admin_head', function() {
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    $dist_url = $theme_root_uri . '/dist/';
    
    // Preload important fonts
    $fonts = [
        'fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
        'fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
        'fonts/Quicksand-Regular.61504eaec8.ttf',
    ];

    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . $dist_url . $font . '" as="font" type="font/ttf" crossorigin>' . "\n";
    }
}, 1);

/**
 * Enqueue login assets.
 *
 * @return void
 */
function app_action_login_enqueue_assets()
{
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-login-js-bundle',
        $template_dir . '/dist/login.js',
        [],
        true
    );

    wp_localize_script('theme-login-js-bundle', 'loginI18n', [
        'userLabel' => __('Ai đang ghé trạm? (Tên / Email)', 'lacadev'),
        'userPlaceholder' => __('Điền tên hoặc email vào đây nhé', 'lacadev'),
        'passLabel' => __('Chìa khóa', 'lacadev'),
        'passPlaceholder' => __('Nhập chìa khóa mở cửa', 'lacadev'),
        'welcomeText' => __('Chào mừng về Trạm Laca!<br/>Cắm sạc, pha trà và bắt đầu nào!', 'lacadev'),
        'forgetPwd' => __('Rớt chìa khoá?', 'lacadev'),
        'backToBlog' => __('← Rời khỏi Trạm', 'lacadev'),
        'language' => get_bloginfo('language')
    ]);

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        'theme-login-css-bundle',
        $template_dir . '/dist/styles/login.css'
    );
}

/**
 * Enqueue editor assets.
 *
 * @return void
 */
function app_action_editor_enqueue_assets()
{
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-editor-js-bundle',
        $template_dir . '/dist/editor.js',
        [],
        true
    );

    /**
    * Enqueue styles.
    */
    Assets::enqueueStyle(
        'theme-editor-css-bundle',
        $template_dir . '/dist/styles/editor.css'
    );

    // Support for block editor styles (classic and modern)
    add_editor_style($template_dir . '/dist/styles/editor.css');

    // Inject theme colors and fonts as CSS variables for the editor
    $primary_color = getOption('primary_color');
    $secondary_color = getOption('secondary_color');
    $bg_color = getOption('bg_color');
    
    $primary_color_dark = getOption('primary_color_dark');
    $secondary_color_dark = getOption('secondary_color_dark');
    $bg_color_dark = getOption('bg_color_dark');

    $custom_css = "
        :root, .editor-styles-wrapper {
            --primary-color: {$primary_color};
            --secondary-color: {$secondary_color};
            --bg-color: {$bg_color};
            --primary-color-dark: {$primary_color_dark};
            --secondary-color-dark: {$secondary_color_dark};
            --bg-color-dark: {$bg_color_dark};
            font-family: 'Quicksand', sans-serif !important;
        }
    ";
    wp_add_inline_style('theme-editor-css-bundle', $custom_css);
}

/**
 * Add favicon proxy.
 *
 * @return void
 * @link WPEmergeTheme\Assets\Assets::addFavicon()
 */
function app_action_add_favicon()
{
    Assets::addFavicon();
}

/**
 * Advanced script optimization with defer/async/preload
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    // Scripts to defer (non-critical)
    // NOTE: theme-vendors-js is NOT deferred - it must load blocking to ensure Swal/dependencies are available
    $defer_scripts = [
        'theme-js-bundle',
        'theme-admin-js-bundle',
        'theme-login-js-bundle',
        'theme-editor-js-bundle',
        'theme-archive-js',
        'theme-comments-js'
    ];

    // Scripts to async (tracking, analytics)
    $async_scripts = [
        'google-analytics',
        'facebook-pixel',
        'hotjar'
    ];

    if (in_array($handle, $defer_scripts)) {
        return str_replace('<script ', '<script defer ', $tag);
    }

    if (in_array($handle, $async_scripts)) {
        return str_replace('<script ', '<script async ', $tag);
    }

    return $tag;
}, 10, 3);

/**
 * Advanced style optimization
 */
add_filter('style_loader_tag', function ($tag, $handle, $href) {
    // Non-critical styles to load asynchronously
    $non_critical_styles = [
        'theme-single-css',
        'fontawesome',
        'google-fonts'
    ];

    // If critical CSS file exists (inlined in header), load main bundle asynchronously
    if (file_exists(dirname(get_stylesheet_directory()) . '/dist/styles/critical.css')) {
        $non_critical_styles[] = 'theme-css-bundle';
    }

    if (in_array($handle, $non_critical_styles)) {
        // Load non-critical CSS asynchronously
        return '<link rel="preload" href="' . $href . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . $handle . '">' .
            '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>';
    }

    return $tag;
}, 10, 3);

/**
 * FIXED: Preload critical assets in wp_head (Agent Skills: Performance)
 */
add_action('wp_head', function() {
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';
    
    // 1. Preload Critical JS
    if (file_exists($dist_path . 'critical.js')) {
        echo '<link rel="preload" href="' . $dist_url . 'critical.js" as="script">' . "\n";
    }

    // 2. Preload Main CSS Bundle (if not using Critical CSS inline)
    if (!file_exists($dist_path . 'styles/critical.css')) {
         echo '<link rel="preload" href="' . $dist_url . 'styles/theme.css" as="style">' . "\n";
    }

    // 3. Preload important fonts (Agent Skills: Performance)
    $fonts = [
        'fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
        'fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
        'fonts/Quicksand-Regular.61504eaec8.ttf',
    ];

    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . $dist_url . $font . '" as="font" type="font/ttf" crossorigin>' . "\n";
    }
}, 1);

/**
 * Enhanced resource hints for performance
 */
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    if ('preconnect' === $relation_type) {
        $hints[] = 'https://fonts.gstatic.com';
        $hints[] = 'https://ajax.googleapis.com';
    }

    if ('dns-prefetch' === $relation_type) {
        $hints[] = '//fonts.googleapis.com';
        $hints[] = '//cdnjs.cloudflare.com';
    }

    if ('prefetch' === $relation_type && (is_home() || is_front_page())) {
        // Prefetch likely next pages
        $hints[] = get_permalink(get_option('page_for_posts'));
    }

    return $hints;
}, 10, 2);

// NOTE: Các hooks được đăng ký trong app/hooks.php — không thêm lại ở đây để tránh duplicate.
