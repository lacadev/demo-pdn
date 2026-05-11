<?php
namespace App\Settings;

/**
 * ThemeControlCenter
 *
 * A single consolidated admin page ("Appearance > Theme Control") that
 * exposes every theme option category in one tabbed UI — replacing the
 * scattered individual Carbon Fields / wp-admin pages.
 *
 * Tabs are registered via the `lacadev/control-center/tabs` filter so
 * child themes and plugins can add their own sections without touching
 * this file.
 *
 * Tab definition:
 *   [
 *     'id'       => 'my_tab',          // unique slug
 *     'label'    => 'My Settings',     // sidebar label
 *     'icon'     => '⚙',              // emoji or dashicon class
 *     'render'   => callable,          // fn() — outputs HTML panel content
 *     'save'     => callable|null,     // fn(array $post_data) — processes save (optional)
 *     'cap'      => 'manage_options',  // capability required (optional)
 *   ]
 *
 * Save flow: each tab's `save` callable is called only when its own
 * `_laca_cc_tab` hidden field matches the submitted tab ID.
 * Each callable receives the raw `$_POST` array (already nonce-verified).
 *
 * @package App\Settings
 */
class ThemeControlCenter
{
    private const MENU_SLUG = 'lacadev-control-center';
    private const PARENT_SLUG = 'laca-admin';
    private const NONCE_KEY = 'laca_cc_save';

    public function init(): void
    {
        add_action('admin_menu',             [$this, 'registerPage'], 99);
        add_action('admin_menu',             [$this, 'hideAppearanceSubmenu'], 100);
        add_action('admin_enqueue_scripts',  [$this, 'enqueueAssets']);
        add_action('admin_init',             [$this, 'redirectLegacyPageUrl']);
        add_action('template_redirect',      [$this, 'redirectPrettyAdminPageUrl']);
        add_action('admin_post_laca_cc_save',[$this, 'handleSave']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Menu registration
    // ─────────────────────────────────────────────────────────────────────

    public function registerPage(): void
    {
        add_theme_page(
            'Laca Theme Settings',
            'Theme Settings',
            'read',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function hideAppearanceSubmenu(): void
    {
        remove_submenu_page('themes.php', self::MENU_SLUG);
    }

    public function redirectLegacyPageUrl(): void
    {
        global $pagenow;

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($pagenow !== 'admin.php' || $page !== self::MENU_SLUG) {
            return;
        }

        $args = [];
        if (isset($_GET['tab'])) {
            $args['tab'] = sanitize_key(wp_unslash($_GET['tab']));
        }
        if (isset($_GET['saved'])) {
            $args['saved'] = sanitize_key(wp_unslash($_GET['saved']));
        }

        wp_safe_redirect($this->getPageUrl($args));
        exit;
    }

    public function redirectPrettyAdminPageUrl(): void
    {
        $requestPath = isset($_SERVER['REQUEST_URI'])
            ? (string) parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
            : '';

        if ($requestPath === '') {
            return;
        }

        $requestPath = untrailingslashit($requestPath);
        if (!str_ends_with($requestPath, '/wp-admin/' . self::MENU_SLUG)) {
            return;
        }

        wp_safe_redirect($this->getPageUrl());
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Page render
    // ─────────────────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!$this->canAccessPage()) {
            wp_die(__('Bạn không có quyền truy cập trang này.'));
        }

        $tabs       = $this->getTabs();
        $activeTab  = sanitize_key($_GET['tab'] ?? array_key_first($tabs));
        if (!isset($tabs[$activeTab])) {
            $activeTab = array_key_first($tabs);
        }

        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        ?>
        <div class="wrap laca-cc">
            <h1 class="laca-cc__heading">
                <span>Theme Settings</span>
                <span class="laca-cc__version">v<?php echo esc_html(wp_get_theme()->get('Version')); ?></span>
            </h1>

            <?php if ($saved): ?>
            <div class="notice notice-success is-dismissible">
                <p>✓ Đã lưu cài đặt thành công.</p>
            </div>
            <?php endif; ?>

            <main class="laca-cc__panel" id="laca-cc-panel">
                <?php
                $activeTabDef = $tabs[$activeTab] ?? null;
                if ($activeTabDef && is_callable($activeTabDef['render'] ?? null)):
                    $hasSave = is_callable($activeTabDef['save'] ?? null);
                    if ($hasSave): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field(self::NONCE_KEY, '_laca_cc_nonce'); ?>
                        <input type="hidden" name="action"       value="laca_cc_save">
                        <input type="hidden" name="_laca_cc_tab" value="<?php echo esc_attr($activeTab); ?>">
                        <input type="hidden" name="_laca_cc_redirect"
                               value="<?php echo esc_url($this->getPageUrl(['tab' => $activeTab, 'saved' => '1'])); ?>">
                    <?php endif; ?>

                    <div class="laca-cc__panel-content">
                        <?php ($activeTabDef['render'])(); ?>
                    </div>

                    <?php if ($hasSave): ?>
                        <div class="laca-cc__panel-footer">
                            <?php submit_button('Lưu thay đổi', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                    <?php endif; ?>

                <?php else: ?>
                    <p>Tab không tìm thấy.</p>
                <?php endif; ?>
            </main>
        </div><!-- .wrap -->
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Save handler
    // ─────────────────────────────────────────────────────────────────────

    public function handleSave(): void
    {
        if (!check_admin_referer(self::NONCE_KEY, '_laca_cc_nonce')) {
            wp_die('Security check failed.');
        }
        if (!$this->canAccessPage()) {
            wp_die('Unauthorized.');
        }

        $tabId    = sanitize_key($_POST['_laca_cc_tab'] ?? '');
        $redirect = esc_url_raw($_POST['_laca_cc_redirect'] ?? $this->getPageUrl());
        $tabs     = $this->getTabs();

        if (isset($tabs[$tabId]) && is_callable($tabs[$tabId]['save'] ?? null)) {
            ($tabs[$tabId]['save'])($_POST);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tab registry
    // ─────────────────────────────────────────────────────────────────────

    private function getTabs(): array
    {
        $tabs = $this->defaultTabs();
        return (array) apply_filters('lacadev/control-center/tabs', $tabs);
    }

    private function canAccessPage(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_theme_options');
    }

    private function getPageUrl(array $args = []): string
    {
        $url = admin_url('themes.php?page=' . rawurlencode(self::MENU_SLUG));

        if ($args === []) {
            return $url;
        }

        return add_query_arg($args, $url);
    }

    private function defaultTabs(): array
    {
        return [

            // ── General ──────────────────────────────────────────────────
            'general' => [
                'label' => 'Tổng quan',
                'icon'  => '🏠',
                'render'=> [$this, 'renderGeneralTab'],
                'save'  => [$this, 'saveGeneralTab'],
            ],

            // ── CTA ───────────────────────────────────────────────────────
            'cta' => [
                'label' => 'Sticky CTA',
                'icon'  => '📣',
                'render'=> [$this, 'renderCtaTab'],
                'save'  => [$this, 'saveCtaTab'],
            ],

            // ── Author ────────────────────────────────────────────────────
            'author' => [
                'label' => 'Author Profile',
                'icon'  => '👤',
                'render'=> [$this, 'renderAuthorTab'],
                'save'  => [$this, 'saveAuthorTab'],
            ],

            // ── Performance ───────────────────────────────────────────────
            'performance' => [
                'label' => 'Performance',
                'icon'  => '⚡',
                'render'=> [$this, 'renderPerformanceTab'],
                'save'  => [$this, 'savePerformanceTab'],
            ],

            // ── Search ────────────────────────────────────────────────────
            'search' => [
                'label' => 'Smart Search',
                'icon'  => '🔍',
                'render'=> [$this, 'renderSearchTab'],
                'save'  => null,
            ],

            // ── System info (read-only) ───────────────────────────────────
            'system' => [
                'label' => 'System Info',
                'icon'  => '🖥',
                'render'=> [$this, 'renderSystemTab'],
                'save'  => null,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Default tab renderers
    // ─────────────────────────────────────────────────────────────────────

    private function renderGeneralTab(): void
    {
        $readingMode = get_option('laca_reading_mode_enabled', '1');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th>Reading Mode</th>
                <td>
                    <label>
                        <input type="checkbox" name="laca_reading_mode_enabled" value="1"
                               <?php checked('1', $readingMode); ?>>
                        Bật nút Reading Mode trên bài viết
                    </label>
                    <p class="description">Khi tắt, theme sẽ không hiển thị nút chuyển sang chế độ đọc tập trung ở frontend.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function saveGeneralTab(array $post): void
    {
        update_option('laca_reading_mode_enabled',  isset($post['laca_reading_mode_enabled']) ? '1' : '0');
    }

    private function renderCtaTab(): void
    {
        $enabled       = get_option('laca_cta_enabled',       '1');
        $hideLoggedIn  = get_option('laca_cta_hide_logged_in','0');
        $showDesktop   = get_option('laca_cta_show_desktop',  '1');
        $showMobile    = get_option('laca_cta_show_mobile',   '1');
        $contactUrl    = get_option('laca_cta_contact_url',   home_url('/lien-he/'));
        $servicesUrl   = get_option('laca_cta_services_url',  home_url('/dich-vu/'));
        $productLabel  = get_option('laca_cta_product_label', 'Mua ngay');
        $blogColor     = sanitize_hex_color(get_option('laca_cta_color_blog',    '#1a6b8a')) ?: '#1a6b8a';
        $homeColor     = sanitize_hex_color(get_option('laca_cta_color_home',    '#2563eb')) ?: '#2563eb';
        $pageColor     = sanitize_hex_color(get_option('laca_cta_color_page',    '#2563eb')) ?: '#2563eb';
        $productColor  = sanitize_hex_color(get_option('laca_cta_color_product', '#16a34a')) ?: '#16a34a';
        $archiveColor  = sanitize_hex_color(get_option('laca_cta_color_archive', '#2563eb')) ?: '#2563eb';
        $blogLabel     = get_option('laca_cta_blog_label',    'Nhận tư vấn miễn phí →');
        $homeLabel     = get_option('laca_cta_home_label',    'Liên hệ ngay →');
        $pageLabel     = get_option('laca_cta_page_label',    'Bắt đầu dự án →');
        $archiveLabel  = get_option('laca_cta_archive_label', 'Xem tất cả dịch vụ →');
        ?>
        <div class="laca-cta-admin" data-laca-cta-admin>
            <div class="laca-cta-admin__settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Trạng thái</th>
                        <td>
                            <label><input type="checkbox" name="laca_cta_enabled" value="1" data-laca-cta-preview="enabled" <?php checked('1',$enabled); ?>>
                            Bật Sticky CTA bar</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Hiển thị theo thiết bị</th>
                        <td>
                            <label class="laca-cc-inline-check"><input type="checkbox" name="laca_cta_show_desktop" value="1" data-laca-cta-preview="desktop" <?php checked('1',$showDesktop); ?>>
                            Desktop</label>
                            <label class="laca-cc-inline-check"><input type="checkbox" name="laca_cta_show_mobile" value="1" data-laca-cta-preview="mobile" <?php checked('1',$showMobile); ?>>
                            Mobile</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Ẩn với user đã đăng nhập</th>
                        <td>
                            <label><input type="checkbox" name="laca_cta_hide_logged_in" value="1" <?php checked('1',$hideLoggedIn); ?>>
                            Ẩn CTA với người dùng đã đăng nhập</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_contact_url">URL Liên hệ</label></th>
                        <td><input type="url" id="laca_cta_contact_url" name="laca_cta_contact_url"
                                   value="<?php echo esc_attr($contactUrl); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_services_url">URL Dịch vụ</label></th>
                        <td><input type="url" id="laca_cta_services_url" name="laca_cta_services_url"
                                   value="<?php echo esc_attr($servicesUrl); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_blog_label">Label — Bài viết</label></th>
                        <td><input type="text" id="laca_cta_blog_label" name="laca_cta_blog_label"
                                   value="<?php echo esc_attr($blogLabel); ?>" class="regular-text" data-laca-cta-preview="blog">
                            <p class="description">Click vao label nay se di den URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_home_label">Label — Trang chủ</label></th>
                        <td><input type="text" id="laca_cta_home_label" name="laca_cta_home_label"
                                   value="<?php echo esc_attr($homeLabel); ?>" class="regular-text" data-laca-cta-preview="home">
                            <p class="description">Click vao label nay se di den URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_page_label">Label — Pages</label></th>
                        <td><input type="text" id="laca_cta_page_label" name="laca_cta_page_label"
                                   value="<?php echo esc_attr($pageLabel); ?>" class="regular-text" data-laca-cta-preview="page">
                            <p class="description">Click vao label nay se di den URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_product_label">Label - Product</label></th>
                        <td><input type="text" id="laca_cta_product_label" name="laca_cta_product_label"
                                   value="<?php echo esc_attr($productLabel); ?>" class="regular-text" data-laca-cta-preview="product">
                            <p class="description">Click vao label nay se di den gio hang WooCommerce; neu khong co WooCommerce thi ve URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_archive_label">Label — Archive / Search</label></th>
                        <td><input type="text" id="laca_cta_archive_label" name="laca_cta_archive_label"
                                   value="<?php echo esc_attr($archiveLabel); ?>" class="regular-text" data-laca-cta-preview="archive">
                            <p class="description">Click vao label nay se di den URL Dich vu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Mau nen CTA</th>
                        <td>
                            <div class="laca-cta-color-grid">
                                <label>Bai viet <input type="color" name="laca_cta_color_blog" value="<?php echo esc_attr($blogColor); ?>" data-laca-cta-color="blog"></label>
                                <label>Trang chu <input type="color" name="laca_cta_color_home" value="<?php echo esc_attr($homeColor); ?>" data-laca-cta-color="home"></label>
                                <label>Pages <input type="color" name="laca_cta_color_page" value="<?php echo esc_attr($pageColor); ?>" data-laca-cta-color="page"></label>
                                <label>Product <input type="color" name="laca_cta_color_product" value="<?php echo esc_attr($productColor); ?>" data-laca-cta-color="product"></label>
                                <label>Archive / Search <input type="color" name="laca_cta_color_archive" value="<?php echo esc_attr($archiveColor); ?>" data-laca-cta-color="archive"></label>
                            </div>
                            <p class="description">Chon context trong preview de xem mau nen thay doi realtime.</p>
                        </td>
                    </tr>
                </table>
            </div>
            <aside class="laca-cta-preview" aria-label="Sticky CTA preview">
                <div class="laca-cta-preview__head">
                    <h3>Preview realtime</h3>
                    <select id="laca_cta_preview_context" data-laca-cta-preview="context">
                        <option value="blog">Bài viết</option>
                        <option value="home">Trang chủ</option>
                        <option value="page">Page</option>
                        <option value="product">Product</option>
                        <option value="archive">Archive / Search</option>
                    </select>
                </div>
                <div class="laca-cta-preview__grid">
                    <div class="laca-cta-preview__device" data-laca-preview-device="desktop">
                        <div class="laca-cta-preview__label">Desktop</div>
                        <div class="laca-cta-preview__screen laca-cta-preview__screen--desktop">
                            <div class="laca-cta-preview__page"></div>
                            <div class="laca-cta-preview__bar">
                                <a href="#" class="laca-cta-preview__button"><span data-laca-preview-label><?php echo esc_html($blogLabel); ?></span></a>
                                <button type="button" aria-label="Đóng">×</button>
                            </div>
                            <div class="laca-cta-preview__hidden">Đang ẩn trên desktop</div>
                        </div>
                    </div>
                    <div class="laca-cta-preview__device" data-laca-preview-device="mobile">
                        <div class="laca-cta-preview__label">Mobile</div>
                        <div class="laca-cta-preview__screen laca-cta-preview__screen--mobile">
                            <div class="laca-cta-preview__page"></div>
                            <div class="laca-cta-preview__bar">
                                <a href="#" class="laca-cta-preview__button"><span data-laca-preview-label><?php echo esc_html($blogLabel); ?></span></a>
                                <button type="button" aria-label="Đóng">×</button>
                            </div>
                            <div class="laca-cta-preview__hidden">Đang ẩn trên mobile</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
        <script>
        (function() {
            var root = document.querySelector('[data-laca-cta-admin]');
            if (!root) return;

            var context = root.querySelector('[data-laca-cta-preview="context"]');
            var enabled = root.querySelector('[data-laca-cta-preview="enabled"]');
            var desktop = root.querySelector('[data-laca-cta-preview="desktop"]');
            var mobile = root.querySelector('[data-laca-cta-preview="mobile"]');
            var labels = {
                blog: root.querySelector('[data-laca-cta-preview="blog"]'),
                home: root.querySelector('[data-laca-cta-preview="home"]'),
                page: root.querySelector('[data-laca-cta-preview="page"]'),
                product: root.querySelector('[data-laca-cta-preview="product"]'),
                archive: root.querySelector('[data-laca-cta-preview="archive"]')
            };
            var colors = {
                blog: root.querySelector('[data-laca-cta-color="blog"]'),
                home: root.querySelector('[data-laca-cta-color="home"]'),
                page: root.querySelector('[data-laca-cta-color="page"]'),
                product: root.querySelector('[data-laca-cta-color="product"]'),
                archive: root.querySelector('[data-laca-cta-color="archive"]')
            };

            function updatePreview() {
                var key = context ? context.value : 'blog';
                var text = labels[key] && labels[key].value ? labels[key].value : 'Sticky CTA';
                root.querySelectorAll('[data-laca-preview-label]').forEach(function(node) {
                    node.textContent = text;
                });
                root.querySelectorAll('.laca-cta-preview__bar').forEach(function(node) {
                    node.style.backgroundColor = colors[key] && colors[key].value ? colors[key].value : '#2563eb';
                });

                root.querySelectorAll('[data-laca-preview-device]').forEach(function(device) {
                    var isDesktop = device.getAttribute('data-laca-preview-device') === 'desktop';
                    var visible = enabled.checked && (isDesktop ? desktop.checked : mobile.checked);
                    device.classList.toggle('is-hidden', !visible);
                });
            }

            root.querySelectorAll('input, select').forEach(function(input) {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });

            updatePreview();
        }());
        </script>
        <?php
    }

    private function saveCtaTab(array $post): void
    {
        update_option('laca_cta_enabled',        isset($post['laca_cta_enabled'])        ? '1' : '0');
        update_option('laca_cta_hide_logged_in', isset($post['laca_cta_hide_logged_in']) ? '1' : '0');
        update_option('laca_cta_show_desktop',   isset($post['laca_cta_show_desktop'])   ? '1' : '0');
        update_option('laca_cta_show_mobile',    isset($post['laca_cta_show_mobile'])    ? '1' : '0');
        update_option('laca_cta_contact_url',    esc_url_raw($post['laca_cta_contact_url']  ?? ''));
        update_option('laca_cta_services_url',   esc_url_raw($post['laca_cta_services_url'] ?? ''));
        update_option('laca_cta_blog_label',     sanitize_text_field($post['laca_cta_blog_label']    ?? ''));
        update_option('laca_cta_home_label',     sanitize_text_field($post['laca_cta_home_label']    ?? ''));
        update_option('laca_cta_page_label',     sanitize_text_field($post['laca_cta_page_label']    ?? ''));
        update_option('laca_cta_product_label',  sanitize_text_field($post['laca_cta_product_label'] ?? ''));
        update_option('laca_cta_archive_label',  sanitize_text_field($post['laca_cta_archive_label'] ?? ''));
        update_option('laca_cta_color_blog',     sanitize_hex_color($post['laca_cta_color_blog']     ?? '') ?: '#1a6b8a');
        update_option('laca_cta_color_home',     sanitize_hex_color($post['laca_cta_color_home']     ?? '') ?: '#2563eb');
        update_option('laca_cta_color_page',     sanitize_hex_color($post['laca_cta_color_page']     ?? '') ?: '#2563eb');
        update_option('laca_cta_color_product',  sanitize_hex_color($post['laca_cta_color_product']  ?? '') ?: '#16a34a');
        update_option('laca_cta_color_archive',  sanitize_hex_color($post['laca_cta_color_archive']  ?? '') ?: '#2563eb');
    }

    private function renderAuthorTab(): void
    {
        $autoAppend = get_option('laca_author_bio_auto_append', '0');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th>Auto-append bio box</th>
                <td>
                    <label>
                        <input type="checkbox" name="laca_author_bio_auto_append" value="1"
                               <?php checked('1', $autoAppend); ?>>
                        Tự động thêm Author Bio Box vào cuối mỗi bài viết đơn
                    </label>
                    <p class="description">
                        Nếu tắt, sử dụng shortcode <code>[laca_author_bio]</code> trong template.
                    </p>
                </td>
            </tr>
        </table>
        <h3>Shortcodes</h3>
        <table class="widefat striped" style="max-width:600px">
            <thead><tr><th>Shortcode</th><th>Mô tả</th></tr></thead>
            <tbody>
                <tr><td><code>[laca_author_bio]</code></td><td>Hiển thị bio box của tác giả bài viết hiện tại</td></tr>
                <tr><td><code>[laca_author_bio user_id="5"]</code></td><td>Hiển thị bio box của user cụ thể</td></tr>
                <tr><td><code>[laca_recommendations]</code></td><td>Hiển thị 3 bài viết liên quan có điểm cao nhất</td></tr>
                <tr><td><code>[laca_recommendations count="6"]</code></td><td>Hiển thị N bài liên quan</td></tr>
                <tr><td><code>[laca_lead_form id="123"]</code></td><td>Multi-step lead form</td></tr>
            </tbody>
        </table>
        <?php
    }

    private function saveAuthorTab(array $post): void
    {
        update_option('laca_author_bio_auto_append', isset($post['laca_author_bio_auto_append']) ? '1' : '0');
    }

    private function renderPerformanceTab(): void
    {
        $cruxApiKey = get_option('laca_crux_api_key', '');
        $cruxUrl    = get_option('laca_crux_url',     home_url('/'));
        ?>
        <h3>Core Web Vitals (CrUX)</h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="laca_crux_api_key">CrUX API Key</label></th>
                <td>
                    <input type="text" id="laca_crux_api_key" name="laca_crux_api_key"
                           value="<?php echo esc_attr($cruxApiKey); ?>" class="regular-text"
                           placeholder="AIza…">
                    <p class="description">
                        Lấy tại <strong>Google Cloud Console</strong> → APIs & Services → Credentials.
                        Không bắt buộc nhưng tăng quota từ 150 → 1500 req/ngày.
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="laca_crux_url">URL cần đo</label></th>
                <td>
                    <input type="url" id="laca_crux_url" name="laca_crux_url"
                           value="<?php echo esc_attr($cruxUrl); ?>" class="regular-text">
                    <p class="description">URL đại diện (thường là trang chủ). CrUX dùng field data thực tế.</p>
                </td>
            </tr>
        </table>

        <h3>Image Optimization</h3>
        <?php
        $avifSupport = function_exists('lacadev_imagick_supports_avif') && lacadev_imagick_supports_avif();
        $webpSupport = extension_loaded('imagick') || function_exists('imagewebp');
        ?>
        <table class="widefat striped" style="max-width:500px;margin-bottom:20px">
            <thead><tr><th>Format</th><th>Status</th></tr></thead>
            <tbody>
                <tr>
                    <td>WebP</td>
                    <td><?php echo $webpSupport ? '<span style="color:green">✓ Hỗ trợ</span>' : '<span style="color:red">✗ Không hỗ trợ</span>'; ?></td>
                </tr>
                <tr>
                    <td>AVIF</td>
                    <td><?php echo $avifSupport ? '<span style="color:green">✓ Hỗ trợ</span>' : '<span style="color:#999">— Cần Imagick + libavif</span>'; ?></td>
                </tr>
                <tr>
                    <td>LQIP</td>
                    <td><?php echo extension_loaded('imagick') ? '<span style="color:green">✓ Hỗ trợ</span>' : '<span style="color:#999">— Cần Imagick</span>'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function savePerformanceTab(array $post): void
    {
        update_option('laca_crux_api_key', sanitize_text_field($post['laca_crux_api_key'] ?? ''));
        update_option('laca_crux_url',     esc_url_raw($post['laca_crux_url'] ?? ''));
        delete_transient('laca_cwv_data'); // bust CWV cache when URL/key changes
    }

    private function renderSearchTab(): void
    {
        $indexUrl = rest_url('lacadev/v1/search-index');
        ?>
        <h3>Smart Search Index</h3>
        <p>Search index được xây dựng từ WP REST API và cache tại client bằng Fuse.js.</p>
        <table class="form-table" role="presentation">
            <tr>
                <th>Index endpoint</th>
                <td>
                    <code><?php echo esc_html($indexUrl); ?></code>
                    <br><br>
                    <a href="<?php echo esc_url($indexUrl); ?>" target="_blank" class="button button-secondary">
                        Xem index JSON ↗
                    </a>
                    <button type="button" class="button" id="laca-bust-search-index"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('laca_bust_search_index')); ?>">
                        ↻ Xóa cache index
                    </button>
                </td>
            </tr>
        </table>
        <script>
        document.getElementById('laca-bust-search-index')?.addEventListener('click', function(){
            this.disabled = true;
            this.textContent = 'Đang xóa…';
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=laca_bust_search_index&nonce=' + this.dataset.nonce
            }).then(r => r.json()).then(res => {
                this.disabled = false;
                this.textContent = res.success ? '✓ Đã xóa cache' : '✗ Lỗi';
            });
        });
        </script>
        <?php
    }

    private function renderSystemTab(): void
    {
        global $wpdb;
        $theme   = wp_get_theme();
        $parent  = wp_get_theme(get_template());
        ?>
        <table class="widefat striped" style="max-width:600px">
            <thead><tr><th>Key</th><th>Value</th></tr></thead>
            <tbody>
                <tr><td>Theme</td><td><?php echo esc_html($theme->get('Name') . ' v' . $theme->get('Version')); ?></td></tr>
                <tr><td>Parent theme</td><td><?php echo esc_html($parent->get('Name') . ' v' . $parent->get('Version')); ?></td></tr>
                <tr><td>WordPress</td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                <tr><td>PHP</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                <tr><td>MySQL</td><td><?php echo esc_html($wpdb->db_version()); ?></td></tr>
                <tr><td>WP Memory limit</td><td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td></tr>
                <tr><td>Debug mode</td><td><?php echo WP_DEBUG ? '<span style="color:orange">ON</span>' : '<span style="color:green">OFF</span>'; ?></td></tr>
                <tr><td>Home URL</td><td><?php echo esc_html(home_url('/')); ?></td></tr>
                <tr><td>Multisite</td><td><?php echo is_multisite() ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>Object cache</td><td><?php echo wp_using_ext_object_cache() ? 'External (Redis/Memcached)' : 'DB transients'; ?></td></tr>
                <tr><td>Active plugins</td><td><?php echo count(get_option('active_plugins', [])); ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        $allowedHooks = [
            'appearance_page_' . self::MENU_SLUG,
        ];

        if (!in_array($hook, $allowedHooks, true)) {
            return;
        }

        wp_add_inline_style('wp-admin', $this->inlineCss());
    }

    private function inlineCss(): string
    {
        return '
.laca-cc { max-width: 1200px; }
.laca-cc__heading { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.laca-cc__heading span:first-child { font-size: 28px; }
.laca-cc__version { font-size: 12px; color: #999; font-weight: normal; background: #f0f0f0; padding: 2px 8px; border-radius: 10px; }
.laca-cc__panel { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; }
.laca-cc__panel-content { min-height: 200px; }
.laca-cc__panel-footer { padding-top: 16px; border-top: 1px solid #f0f0f0; margin-top: 20px; }
.laca-cc-inline-check { display: inline-flex; align-items: center; gap: 6px; margin-right: 18px; }
.laca-cta-admin { display: grid; grid-template-columns: minmax(420px, 1fr) minmax(340px, 460px); gap: 28px; align-items: start; }
.laca-cta-admin .form-table { margin-top: 0; }
.laca-cta-admin .regular-text { width: min(100%, 350px); }
.laca-cta-color-grid { display: grid; gap: 10px 16px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); max-width: 520px; }
.laca-cta-color-grid label { align-items: center; display: flex; font-weight: 600; gap: 8px; justify-content: space-between; }
.laca-cta-color-grid input { height: 32px; width: 46px; }
.laca-cta-preview { border: 1px solid #dbe1ea; border-radius: 10px; background: #f8fafc; padding: 18px; position: sticky; top: 52px; }
.laca-cta-preview__head { align-items: center; display: flex; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.laca-cta-preview__head h3 { margin: 0; font-size: 14px; }
.laca-cta-preview__grid { display: grid; gap: 16px; }
.laca-cta-preview__label { color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .04em; margin-bottom: 6px; text-transform: uppercase; }
.laca-cta-preview__screen { background: #eef2f7; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; position: relative; }
.laca-cta-preview__screen--desktop { height: 180px; }
.laca-cta-preview__screen--mobile { height: 280px; margin: 0 auto; max-width: 210px; border-radius: 20px; }
.laca-cta-preview__page { height: 100%; background: linear-gradient(#fff 0 0) 24px 22px / 45% 10px no-repeat, linear-gradient(#e2e8f0 0 0) 24px 44px / 72% 8px no-repeat, linear-gradient(#e2e8f0 0 0) 24px 62px / 56% 8px no-repeat, #f8fafc; }
.laca-cta-preview__bar { align-items: center; background: #2563eb; bottom: 0; box-shadow: 0 -8px 20px rgba(15, 23, 42, .16); color: #fff; display: flex; gap: 10px; justify-content: center; left: 0; min-height: 42px; padding: 8px 14px; position: absolute; right: 0; }
.laca-cta-preview__button { color: #fff; display: inline-flex; font-size: 13px; font-weight: 700; max-width: calc(100% - 34px); overflow: hidden; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.laca-cta-preview__bar button { align-items: center; background: transparent; border: 0; color: rgba(255,255,255,.8); display: inline-flex; font-size: 18px; height: 24px; justify-content: center; line-height: 1; margin-left: auto; padding: 0; width: 24px; }
.laca-cta-preview__hidden { align-items: center; background: rgba(248,250,252,.9); color: #64748b; display: none; font-size: 13px; font-weight: 700; inset: 0; justify-content: center; position: absolute; text-align: center; }
.laca-cta-preview__device.is-hidden .laca-cta-preview__bar { display: none; }
.laca-cta-preview__device.is-hidden .laca-cta-preview__hidden { display: flex; }
@media (max-width: 1100px) {
    .laca-cta-admin { grid-template-columns: 1fr; }
    .laca-cta-preview { position: static; }
}
';
    }
}
