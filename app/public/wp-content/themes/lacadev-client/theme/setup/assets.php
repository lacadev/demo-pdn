<?php
/**
 * Asset helpers.
 *
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Theme;
use WPEmergeTheme\Facades\Assets;
use App\Contracts\AssetHandles;

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
        wp_enqueue_script(AssetHandles::CRITICAL_JS, $dist_url . 'critical.js', [], $version, false);
    }

    /**
     * Vendors bundle
     */
    $vendors_deps = [];
    if (file_exists($dist_path . 'vendors.js')) {
        wp_enqueue_script(AssetHandles::VENDORS_JS, $dist_url . 'vendors.js', [], $version, true);
        $vendors_deps = [AssetHandles::VENDORS_JS];
    }

    /**
     * Main JavaScript bundle (deferred)
     */
    Assets::enqueueScript(AssetHandles::THEME_JS, $dist_url . 'theme.js', $vendors_deps, true);

    /**
     * Conditional assets based on page type
     */
    if (is_home() || is_archive() || is_search()) {
        if (file_exists($dist_path . 'archive.js')) {
            wp_enqueue_script(AssetHandles::ARCHIVE_JS, $dist_url . 'archive.js', [AssetHandles::THEME_JS], $version, true);
        }
    }

    if (is_single() && comments_open()) {
        if (file_exists($dist_path . 'comments.js')) {
            wp_enqueue_script(AssetHandles::COMMENTS_JS, $dist_url . 'comments.js', [AssetHandles::THEME_JS], $version, true);
        }
    }

    /**
     * Enqueue styles with preload optimization
     */
    Assets::enqueueStyle(AssetHandles::THEME_CSS, $dist_url . 'styles/theme.css');

    /**
     * Conditional CSS based on page type
     */
    if (is_single()) {
        if (file_exists($dist_path . 'styles/single.css')) {
            wp_enqueue_style(AssetHandles::SINGLE_CSS, $dist_url . 'styles/single.css', [AssetHandles::THEME_CSS], $version);
        }
    }

    /**
     * Enqueue theme's style.css file to allow overrides for the bundled styles.
     */
    Assets::enqueueStyle(AssetHandles::THEME_STYLES, get_template_directory_uri() . '/style.css');

    /**
     * Localize script with minimal data
     */
    wp_localize_script(AssetHandles::THEME_JS, 'themeData', [
        'ajaxurl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('theme_nonce'),
        'searchNonce'   => wp_create_nonce('theme_search_nonce'),
        'searchIndex'   => rest_url('lacadev/v1/search-index'),
        'readingModeEnabled' => get_option('laca_reading_mode_enabled', '1') === '1',
        'isHome'        => is_home(),
        'isMobile'      => wp_is_mobile(),
        'currentUrl'    => get_permalink(),
    ]);

    if (get_option('laca_reading_mode_enabled', '1') !== '1') {
        wp_add_inline_script(
            AssetHandles::THEME_JS,
            <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    try {
        localStorage.removeItem('lacadev_reading_mode');
    } catch (e) {}

    document.body.classList.remove('reading-mode');

    var removeButton = function () {
        var btn = document.getElementById('reading-mode-btn');
        if (btn) {
            btn.remove();
        }
    };

    removeButton();

    var observer = new MutationObserver(function () {
        removeButton();
    });

    observer.observe(document.body, { childList: true, subtree: true });

    setTimeout(function () {
        observer.disconnect();
    }, 5000);
});
JS,
            'after'
        );
    }
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
        AssetHandles::ADMIN_CSS,
        $template_dir . '/dist/styles/admin.css'
    );
    Assets::enqueueStyle(
        AssetHandles::EDITOR_CSS,
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
        wp_enqueue_script(AssetHandles::VENDORS_JS, $vendors_url, [], wp_get_theme()->get('Version'), false);
        $admin_deps = [AssetHandles::VENDORS_JS];
    }

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::ADMIN_JS,
        $template_dir . '/dist/admin.js',
        $admin_deps,
        true
    );

    /**
     * Localize admin script data with nonce for AJAX requests and i18n strings
     */
    wp_localize_script(AssetHandles::ADMIN_JS, 'ajaxurl_params', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('update_post_thumbnail'),  // Must match backend check_ajax_referer
    ]);

    /**
     * Localize i18n strings for admin JavaScript
     */
    wp_localize_script(AssetHandles::ADMIN_JS, 'adminI18n', [
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

        wp_localize_script(AssetHandles::ADMIN_JS, 'lacaProjectCharts', [
            'primary'  => '#2563eb',
            'byStatus' => $by_status,
            'byMonth'  => $by_month,
        ]);
    }

    wp_add_inline_style(AssetHandles::ADMIN_CSS, <<<'CSS'
        :root {
            --laca-admin-bg: #f3f5f9;
            --laca-admin-surface: #ffffff;
            --laca-admin-surface-muted: #f8fafc;
            --laca-admin-surface-strong: #eef2f7;
            --laca-admin-border: #dbe1ea;
            --laca-admin-border-strong: #c8d1de;
            --laca-admin-text: #0f172a;
            --laca-admin-text-muted: #64748b;
            --laca-admin-accent: #2563eb;
            --laca-admin-accent-strong: #1d4ed8;
            --laca-admin-accent-soft: rgba(37, 99, 235, 0.10);
            --laca-admin-danger: #dc2626;
            --laca-admin-success: #15803d;
            --laca-admin-warning: #b45309;
            --laca-admin-shadow: 0 18px 40px rgba(15, 23, 42, 0.07);
            --laca-admin-radius: 14px;
        }

        body.wp-admin,
        #wpcontent {
            background: var(--laca-admin-bg);
            color: var(--laca-admin-text);
        }

        body.wp-admin,
        body.wp-admin input,
        body.wp-admin button,
        body.wp-admin select,
        body.wp-admin textarea {
            font-family: inherit;
        }

        #wpadminbar {
            background: #0f172a !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        #wpadminbar .ab-item,
        #wpadminbar .ab-empty-item,
        #wpadminbar #wp-toolbar span.ab-label,
        #wpadminbar .ab-icon::before,
        #wpadminbar a.ab-item,
        #wpadminbar div.ab-item {
            color: rgba(255, 255, 255, 0.82) !important;
        }

        #wpadminbar .quicklinks > ul > li:hover > .ab-item,
        #wpadminbar .ab-top-menu > li:hover > .ab-item,
        #wpadminbar .ab-top-menu > li > .ab-item:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
        }

        #adminmenuback,
        #adminmenuwrap,
        #adminmenu {
            background: #111827 !important;
            border-right: 1px solid rgba(255, 255, 255, 0.06) !important;
        }

        #adminmenu li.menu-top:hover,
        #adminmenu li.opensub > a.menu-top,
        #adminmenu li > a.menu-top:focus {
            background: rgba(255, 255, 255, 0.06) !important;
            color: #fff !important;
        }

        #adminmenu a,
        #adminmenu div.wp-menu-image::before {
            color: rgba(255, 255, 255, 0.68) !important;
        }

        #adminmenu li.current a.menu-top,
        #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu {
            background: rgba(255, 255, 255, 0.08) !important;
            border-left: 3px solid var(--laca-admin-accent);
            color: #fff !important;
            font-weight: 600;
        }

        #adminmenu .wp-submenu {
            background: #0f172a !important;
        }

        #adminmenu .wp-submenu a {
            color: rgba(255, 255, 255, 0.66) !important;
        }

        #adminmenu .wp-submenu li.current a,
        #adminmenu .wp-submenu a:hover {
            color: #fff !important;
        }

        #wpbody-content > .wrap,
        .auto-fold #wpbody-content > .wrap {
            max-width: 1440px;
            padding-top: 18px;
        }

        .wrap h1,
        .wrap h2,
        .wrap h3,
        .wrap .page-title-action,
        .cf-container__tabs-item,
        .lcf-tab-btn,
        .lcf-pv-tab {
            color: var(--laca-admin-text);
            letter-spacing: 0;
        }

        .page-title-action,
        .button,
        .button-secondary,
        .button-primary,
        #publish {
            border-radius: 10px !important;
            box-shadow: none !important;
            font-weight: 600;
            min-height: 38px;
            padding-inline: 14px !important;
        }

        .button-primary,
        #publish,
        .page-title-action {
            background: var(--laca-admin-accent) !important;
            border-color: var(--laca-admin-accent) !important;
            color: #fff !important;
        }

        .button-primary:hover,
        #publish:hover,
        .page-title-action:hover {
            background: var(--laca-admin-accent-strong) !important;
            border-color: var(--laca-admin-accent-strong) !important;
        }

        .button-secondary {
            border-color: var(--laca-admin-border-strong) !important;
            color: var(--laca-admin-text) !important;
        }

        .button-secondary:hover {
            border-color: var(--laca-admin-accent) !important;
            color: var(--laca-admin-accent) !important;
        }

        .notice,
        div.updated,
        div.error {
            background: var(--laca-admin-surface) !important;
            border: 1px solid var(--laca-admin-border) !important;
            border-left-width: 4px !important;
            border-radius: 12px !important;
            box-shadow: none !important;
        }

        .notice-success {
            border-left-color: var(--laca-admin-success) !important;
        }

        .notice-info {
            border-left-color: var(--laca-admin-accent) !important;
        }

        .notice-warning {
            border-left-color: var(--laca-admin-warning) !important;
        }

        .notice-error {
            border-left-color: var(--laca-admin-danger) !important;
        }

        .postbox,
        .stuffbox,
        .card,
        .cf-container-theme-options .cf-container__fields,
        .wp-list-table,
        .laca-cf-builder-shell,
        .laca-help-card,
        .laca-help-footer,
        .lacadev-stat-box,
        .lacadev-dashboard-grid .stat-item,
        .lacadev-btn-quick,
        .laca-todo-item {
            background: var(--laca-admin-surface) !important;
            border-radius: var(--laca-admin-radius) !important;
            border: 1px solid var(--laca-admin-border) !important;
            box-shadow: var(--laca-admin-shadow) !important;
        }

        .postbox-header,
        .handlediv,
        .cf-container-theme-options .cf-container__tabs,
        .lcf-tabs,
        .lcf-preview-switcher,
        .nav-tab-wrapper {
            background: var(--laca-admin-surface-muted) !important;
            border-bottom: 1px solid var(--laca-admin-border) !important;
        }

        .cf-container__tabs-item--current,
        .lcf-tab-btn.is-active,
        .lcf-pv-tab.is-active,
        .nav-tab-active {
            background: var(--laca-admin-surface) !important;
            color: var(--laca-admin-text) !important;
            border-color: var(--laca-admin-border) !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .cf-container__tabs-item,
        .lcf-tab-btn,
        .lcf-pv-tab,
        .nav-tab {
            color: var(--laca-admin-text-muted) !important;
            font-weight: 600 !important;
            border-radius: 10px 10px 0 0 !important;
        }

        .nav-tab-wrapper {
            border: 0 !important;
            display: flex;
            gap: 8px;
            margin-bottom: 20px !important;
            padding: 8px !important;
        }

        .nav-tab {
            border: 1px solid transparent !important;
            margin-left: 0 !important;
            padding: 9px 14px !important;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="password"],
        input[type="number"],
        input[type="search"],
        textarea,
        select {
            border-color: var(--laca-admin-border-strong) !important;
            border-radius: 10px !important;
            box-shadow: none !important;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="url"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        input[type="search"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--laca-admin-accent) !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
        }

        .form-table th {
            color: var(--laca-admin-text);
            font-weight: 700;
            padding-top: 18px;
        }

        .form-table td,
        .description,
        .forminp p {
            color: var(--laca-admin-text-muted);
        }

        .wp-list-table thead th,
        .wp-list-table tfoot th {
            background: var(--laca-admin-surface-muted);
            color: var(--laca-admin-text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .wp-list-table tbody tr:hover td {
            background: rgba(15, 23, 42, 0.025);
        }

        .lacadev-dashboard-widget,
        .laca-help-wrap {
            color: var(--laca-admin-text);
        }

        .lacadev-stat-box .stat-label,
        .lacadev-dashboard-grid .stat-label,
        .laca-health-list .health-label,
        .laca-help-intro,
        .laca-help-card-content,
        .laca-help-footer,
        .laca-charts-footer {
            color: var(--laca-admin-text-muted) !important;
        }

        .lacadev-btn-quick:hover,
        .laca-todo-item:hover {
            border-color: var(--laca-admin-accent) !important;
            color: var(--laca-admin-accent) !important;
            transform: translateY(-1px);
        }

        .laca-help-header,
        .hub-section-title,
        .laca-help-card h3,
        .laca-chart-block h4 {
            color: var(--laca-admin-text) !important;
        }

        .laca-help-footer {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
        }

        .swal2-popup,
        .alp-welcome-swal {
            background: #ffffff !important;
            border: 1px solid var(--laca-admin-border) !important;
            border-radius: 18px !important;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16) !important;
            color: var(--laca-admin-text) !important;
        }

        .alp-welcome-swal {
            backdrop-filter: none !important;
            max-width: 420px !important;
            padding: 24px !important;
        }

        .alp-welcome-swal .alp-swal-title {
            color: var(--laca-admin-text) !important;
            font-size: 24px !important;
        }

        .alp-welcome-swal .alp-swal-msg {
            color: var(--laca-admin-text-muted) !important;
        }

        .alp-welcome-swal .alp-swal-icon {
            filter: none !important;
        }
CSS
    );
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
    $resolveLogoUrl = static function ($rawValue): string {
        // Carbon Fields image can return ID, URL string, or array.
        if (empty($rawValue)) {
            return '';
        }

        if (is_numeric($rawValue)) {
            $url = wp_get_attachment_image_url((int) $rawValue, 'full');
            return $url ?: '';
        }

        if (is_array($rawValue)) {
            if (!empty($rawValue['url']) && is_string($rawValue['url'])) {
                return esc_url_raw($rawValue['url']);
            }

            if (!empty($rawValue['id']) && is_numeric($rawValue['id'])) {
                $url = wp_get_attachment_image_url((int) $rawValue['id'], 'full');
                return $url ?: '';
            }

            if (!empty($rawValue['value']) && is_numeric($rawValue['value'])) {
                $url = wp_get_attachment_image_url((int) $rawValue['value'], 'full');
                return $url ?: '';
            }

            return '';
        }

        if (is_string($rawValue)) {
            if (filter_var($rawValue, FILTER_VALIDATE_URL)) {
                return esc_url_raw($rawValue);
            }

            if (ctype_digit($rawValue)) {
                $url = wp_get_attachment_image_url((int) $rawValue, 'full');
                return $url ?: '';
            }
        }

        return '';
    };

    $login_logo_raw = carbon_get_theme_option('login_logo');
    $login_logo_url = $resolveLogoUrl($login_logo_raw);
    if (empty($login_logo_url)) {
        $login_logo_url = $resolveLogoUrl(carbon_get_theme_option('logo'));
    }
    $pickLoginI18n = static function (string $key, string $lang, string $fallback) {
        $value = carbon_get_theme_option("{$key}_{$lang}");
        if (empty($value)) {
            // Backward compatibility with old single-language keys.
            $value = carbon_get_theme_option($key);
        }
        return !empty($value) ? $value : $fallback;
    };

    $loginVi = [
        'userLabel' => $pickLoginI18n('login_user_label', 'vi', 'Ai đang ghé trạm?'),
        'userPlaceholder' => $pickLoginI18n('login_user_placeholder', 'vi', 'Điền tên hoặc email vào đây nhé'),
        'passLabel' => $pickLoginI18n('login_password_label', 'vi', 'Chìa khóa'),
        'passPlaceholder' => $pickLoginI18n('login_password_placeholder', 'vi', 'Nhập chìa khóa mở cửa'),
        'welcomeText' => nl2br(sanitize_textarea_field($pickLoginI18n('login_welcome_text', 'vi', "Chào mừng về Trạm Laca!\nCắm sạc, pha trà và bắt đầu nào!"))),
        'forgetPwd' => $pickLoginI18n('login_forgot_label', 'vi', 'Rớt chìa khoá?'),
        'backToBlog' => $pickLoginI18n('login_back_label', 'vi', '← Rời khỏi Trạm'),
    ];

    $loginEn = [
        'userLabel' => $pickLoginI18n('login_user_label', 'en', "Who's visiting the station?"),
        'userPlaceholder' => $pickLoginI18n('login_user_placeholder', 'en', 'Enter name or email here'),
        'passLabel' => $pickLoginI18n('login_password_label', 'en', 'The Key'),
        'passPlaceholder' => $pickLoginI18n('login_password_placeholder', 'en', 'Enter your key to open'),
        'welcomeText' => nl2br(sanitize_textarea_field($pickLoginI18n('login_welcome_text', 'en', "Welcome to Laca Station!\nCharge up, brew some tea and let's go!"))),
        'forgetPwd' => $pickLoginI18n('login_forgot_label', 'en', 'Lost your key?'),
        'backToBlog' => $pickLoginI18n('login_back_label', 'en', '← Leave the Station'),
    ];

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::LOGIN_JS,
        $template_dir . '/dist/login.js',
        [],
        true
    );

    wp_localize_script(AssetHandles::LOGIN_JS, 'loginI18n', [
        'logoUrl' => $login_logo_url,
        'locales' => [
            'vi' => $loginVi,
            'en' => $loginEn,
        ],
        'userLabel' => $loginVi['userLabel'],
        'userPlaceholder' => $loginVi['userPlaceholder'],
        'passLabel' => $loginVi['passLabel'],
        'passPlaceholder' => $loginVi['passPlaceholder'],
        'welcomeText' => $loginVi['welcomeText'],
        'forgetPwd' => $loginVi['forgetPwd'],
        'backToBlog' => $loginVi['backToBlog'],
        'language' => get_bloginfo('language'),
        'homeUrl' => home_url('/'),
    ]);

    // Ensure placeholders can be overridden from Carbon Fields without requiring JS rebuild.
    wp_add_inline_script(AssetHandles::LOGIN_JS, "(function(){document.addEventListener('DOMContentLoaded',function(){var cfg=window.loginI18n||{};var locales=cfg.locales||{};var lang=(document.documentElement.lang||'').indexOf('en')!==-1?'en':'vi';var data=locales[lang]||locales.vi||{};var userPlaceholder=data.userPlaceholder||cfg.userPlaceholder||'';var passPlaceholder=data.passPlaceholder||cfg.passPlaceholder||'';var user=document.getElementById('user_login');var pass=document.getElementById('user_pass');if(user&&userPlaceholder){user.setAttribute('placeholder',userPlaceholder);}if(pass&&passPlaceholder){pass.setAttribute('placeholder',passPlaceholder);}});}());", 'after');

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        AssetHandles::LOGIN_CSS,
        $template_dir . '/dist/styles/login.css'
    );

    // Force override login logo in case theme CSS uses !important.
    if (!empty($login_logo_url)) {
        $safe_logo_url = esc_url_raw($login_logo_url);
        $login_logo_css = "#login h1 a{background-image:url('{$safe_logo_url}') !important;}";
        wp_add_inline_style(AssetHandles::LOGIN_CSS, $login_logo_css);
    }
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
        AssetHandles::EDITOR_JS,
        $template_dir . '/dist/editor.js',
        [],
        true
    );

    /**
    * Enqueue styles.
    */
    Assets::enqueueStyle(
        AssetHandles::EDITOR_CSS,
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
    wp_add_inline_style(AssetHandles::EDITOR_CSS, $custom_css);
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
        AssetHandles::THEME_JS,
        AssetHandles::ADMIN_JS,
        AssetHandles::LOGIN_JS,
        AssetHandles::EDITOR_JS,
        AssetHandles::ARCHIVE_JS,
        AssetHandles::COMMENTS_JS
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
        AssetHandles::SINGLE_CSS,
        'fontawesome',
        'google-fonts'
    ];

    // Note: theme-css-bundle is always loaded blocking to prevent layout issues.
    // Critical CSS is inlined separately in wp_head for above-the-fold performance.

    return $tag;
}, 10, 3);

/**
 * Inline Critical CSS + Preload assets in wp_head
 *
 * When critical.css exists (generated by `yarn critical`), its contents are
 * inlined directly into <head> so layout-essential rules (.container, body, etc.)
 * are available immediately — even though theme.css loads async.
 */
add_action('wp_head', function() {
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';
    
    // 1. Inline Critical CSS (prevents FOUC when theme.css is loaded async)
    $critical_path = $dist_path . 'styles/critical.css';
    if (file_exists($critical_path)) {
        $critical_css = file_get_contents($critical_path);
        if ($critical_css) {
            echo '<style id="critical-css">' . $critical_css . '</style>' . "\n";
        }
    }

    // 2. Preload Critical JS
    if (file_exists($dist_path . 'critical.js')) {
        echo '<link rel="preload" href="' . $dist_url . 'critical.js" as="script">' . "\n";
    }

    // 3. Preload Main CSS Bundle (if NOT using Critical CSS)
    if (!file_exists($critical_path)) {
         echo '<link rel="preload" href="' . $dist_url . 'styles/theme.css" as="style">' . "\n";
    }

    // 4. Preload important fonts
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
