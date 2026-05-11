<?php

namespace App\Settings\LacaAdmin;

/**
 * Groups Laca Admin pages into a compact internal dock.
 *
 * Customer sites should not receive the full internal Laca Projects CRM, but the
 * admin tools still need a predictable, low-noise navigation surface.
 */
class LacaAdminMenuOrganizer
{
    private const PARENT_SLUG = 'laca-admin';

    /**
     * @var array<int,array{key:string,label:string,icon:string,items:array<int,array{label:string,slug:string,url:string}>}>
     */
    private array $navigationGroups = [];

    /**
     * @var array<string,array{label:string,tab:string}>
     */
    private const SECURITY_TABS = [
        'laca-security-audit' => [
            'label' => 'Kiểm tra bảo mật',
            'tab' => 'audit',
        ],
        'laca-security-fim' => [
            'label' => 'Giám sát file',
            'tab' => 'fim',
        ],
        'laca-security-malware' => [
            'label' => 'Quét mã độc',
            'tab' => 'malware',
        ],
        'laca-security-users' => [
            'label' => 'User ẩn',
            'tab' => 'users',
        ],
        'laca-security-login' => [
            'label' => 'URL đăng nhập',
            'tab' => 'login',
        ],
        'laca-security-2fa' => [
            'label' => '2FA TOTP',
            'tab' => '2fa',
        ],
    ];

    /**
     * @var array<string,array{label:string,tab:string}>
     */
    private const THEME_SETTINGS_TABS = [
        'laca-theme-settings-general' => [
            'label' => 'Tổng quan',
            'tab' => 'general',
        ],
        'laca-theme-settings-cta' => [
            'label' => 'Sticky CTA',
            'tab' => 'cta',
        ],
        'laca-theme-settings-author' => [
            'label' => 'Author Profile',
            'tab' => 'author',
        ],
        'laca-theme-settings-performance' => [
            'label' => 'Performance',
            'tab' => 'performance',
        ],
        'laca-theme-settings-search' => [
            'label' => 'Smart Search',
            'tab' => 'search',
        ],
        'laca-theme-settings-system' => [
            'label' => 'System Info',
            'tab' => 'system',
        ],
    ];

    /**
     * @var array<string,array{label:string,icon:string,items:string[]}>
     */
    private const GROUPS = [
        'general' => [
            'label' => 'Tổng quan / Cấu hình chung',
            'icon' => 'dashicons-admin-generic',
            'items' => [
                'laca-admin',
            ],
        ],
        'management_help' => [
            'label' => 'Quản trị & HD Sử dụng',
            'icon' => 'dashicons-welcome-learn-more',
            'items' => [
                'laca-management-dashboard-widgets',
                'laca-help-content-settings',
            ],
        ],
        'theme_settings' => [
            'label' => 'Theme Settings',
            'icon' => 'dashicons-admin-appearance',
            'items' => [
                'laca-theme-settings-general',
                'laca-theme-settings-cta',
                'laca-theme-settings-author',
                'laca-theme-settings-performance',
                'laca-theme-settings-search',
                'laca-theme-settings-system',
            ],
        ],
        'maintenance' => [
            'label' => 'Hiệu năng & bảo trì',
            'icon' => 'dashicons-performance',
            'items' => [
                'laca-tools',
                'laca-db-cleaner',
                'laca-email-log',
            ],
        ],
        'security' => [
            'label' => 'Bảo mật & đăng nhập',
            'icon' => 'dashicons-shield-alt',
            'items' => [
                'laca-security-audit',
                'laca-security-fim',
                'laca-security-malware',
                'laca-security-users',
                'laca-security-login',
                'laca-security-2fa',
                'laca-recaptcha',
                'laca-login-socials',
            ],
        ],
        'content' => [
            'label' => 'Nội dung & cấu trúc',
            'icon' => 'dashicons-screenoptions',
            'items' => [
                'laca-dynamic-cpt',
                'laca-contact-forms',
            ],
        ],
        'client_ops' => [
            'label' => 'Kết nối & vận hành',
            'icon' => 'dashicons-admin-site-alt3',
            'items' => [
                'laca-client-ops',
                'laca-tracker',
                'laca-block-sync',
                'laca-project-notifications',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing & AI',
            'icon' => 'dashicons-megaphone',
            'items' => [
                'laca-exit-popup',
                'laca-chatbot',
            ],
        ],
    ];

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'organize'], PHP_INT_MAX);
        add_action('admin_head', [$this, 'printStyles']);
        add_action('all_admin_notices', [$this, 'renderNavigationDock']);
        add_filter('admin_body_class', [$this, 'filterAdminBodyClass']);
    }

    public function organize(): void
    {
        global $submenu;

        if (empty($submenu[self::PARENT_SLUG]) || !is_array($submenu[self::PARENT_SLUG])) {
            return;
        }

        $itemsBySlug = [];
        $unassigned = [];

        foreach ($submenu[self::PARENT_SLUG] as $item) {
            $slug = (string) ($item[2] ?? '');
            if ($slug === '') {
                continue;
            }

            $itemsBySlug[$slug] = $item;
            $unassigned[$slug] = $item;
        }

        if (current_user_can('edit_theme_options') || current_user_can('manage_options')) {
            $itemsBySlug['lacadev-control-center'] = [
                __('Theme Settings', 'laca'),
                'read',
                'lacadev-control-center',
            ];
            $unassigned['lacadev-control-center'] = $itemsBySlug['lacadev-control-center'];
        }

        $organized = [];
        $navigationGroups = [];

        foreach (self::GROUPS as $groupKey => $group) {
            $groupItems = [];

            foreach ($group['items'] as $slug) {
                if (isset(self::THEME_SETTINGS_TABS[$slug])) {
                    if (!isset($itemsBySlug['lacadev-control-center'])) {
                        continue;
                    }

                    $groupItems[] = $this->buildNavigationItemFromSlug($slug);
                    continue;
                }

                if (isset(self::SECURITY_TABS[$slug])) {
                    if (!isset($itemsBySlug['laca-security'])) {
                        continue;
                    }

                    $groupItems[] = $this->buildNavigationItemFromSlug($slug);
                    continue;
                }

                if (!isset($itemsBySlug[$slug])) {
                    continue;
                }

                $organized[] = $itemsBySlug[$slug];
                $groupItems[] = $this->buildNavigationItem($itemsBySlug[$slug]);
                unset($unassigned[$slug]);
            }

            if ($groupItems !== []) {
                $navigationGroups[] = [
                    'key' => $groupKey,
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'items' => $groupItems,
                ];
            }
        }

        if (isset($itemsBySlug['laca-security'])) {
            $organized[] = $itemsBySlug['laca-security'];
            unset($unassigned['laca-security']);
        }

        if (isset($itemsBySlug['lacadev-control-center'])) {
            $organized[] = $itemsBySlug['lacadev-control-center'];
            unset($unassigned['lacadev-control-center']);
        }

        if ($unassigned !== []) {
            array_push($organized, ...array_values($unassigned));
            $navigationGroups[] = [
                'key' => 'other',
                'label' => 'Khác',
                'icon' => 'dashicons-menu-alt3',
                'items' => array_map([$this, 'buildNavigationItem'], array_values($unassigned)),
            ];
        }

        $submenu[self::PARENT_SLUG] = $organized;
        $this->navigationGroups = $navigationGroups;
    }

    /**
     * @param array<int,mixed> $item
     *
     * @return array{label:string,slug:string,url:string}
     */
    private function buildNavigationItem(array $item): array
    {
        $slug = (string) ($item[2] ?? '');
        $label = wp_strip_all_tags((string) ($item[0] ?? $slug));

        return $this->buildNavigationItemFromSlug($slug, $label);
    }

    /**
     * @return array{label:string,slug:string,url:string}
     */
    private function buildNavigationItemFromSlug(string $slug, ?string $label = null): array
    {
        if (isset(self::SECURITY_TABS[$slug])) {
            $tab = self::SECURITY_TABS[$slug]['tab'];

            return [
                'label' => self::SECURITY_TABS[$slug]['label'],
                'slug' => $slug,
                'url' => add_query_arg(
                    [
                        'page' => 'laca-security',
                        'tab' => $tab,
                    ],
                    admin_url('admin.php')
                ),
            ];
        }

        if (isset(self::THEME_SETTINGS_TABS[$slug])) {
            $tab = self::THEME_SETTINGS_TABS[$slug]['tab'];

            return [
                'label' => self::THEME_SETTINGS_TABS[$slug]['label'],
                'slug' => $slug,
                'url' => add_query_arg(
                    [
                        'page' => 'lacadev-control-center',
                        'tab' => $tab,
                    ],
                    admin_url('themes.php')
                ),
            ];
        }

        $url = $slug === 'lacadev-control-center'
            ? admin_url('themes.php?page=' . rawurlencode($slug))
            : admin_url('admin.php?page=' . rawurlencode($slug));

        return [
            'label' => ($label !== null && $label !== '') ? $label : $slug,
            'slug' => $slug,
            'url' => $url,
        ];
    }

    public function filterAdminBodyClass(string $classes): string
    {
        if (!$this->isLacaAdminRequest()) {
            return $classes;
        }

        return trim($classes . ' laca-admin-dock-active');
    }

    public function printStyles(): void
    {
        ?>
        <style>
            #toplevel_page_laca-admin .wp-submenu {
                display: none !important;
            }

            body.laca-admin-dock-active:not(.folded) #wpcontent,
            body.laca-admin-dock-active.folded #wpcontent {
                padding-left: 332px;
            }

            .laca-admin-dock {
                background: #ffffff;
                border-right: 1px solid #dbe1ea;
                bottom: 0;
                box-shadow: 12px 0 36px rgba(15, 23, 42, 0.08);
                color: #0f172a;
                left: 160px;
                overflow-y: auto;
                padding: 24px 18px 30px;
                position: fixed;
                top: 32px;
                width: 316px;
                z-index: 99;
            }

            body.folded .laca-admin-dock {
                left: 36px;
            }

            .laca-admin-dock__group {
                border-top: 1px solid #e7ecf3;
                margin-bottom: 0;
                padding: 18px 2px 0;
                margin-top: 18px;
            }

            .laca-admin-dock__group:first-of-type {
                border-top: 0;
                padding-top: 0;
                margin-top: 0;
            }

            .laca-admin-dock__group-title {
                align-items: center;
                color: #64748b;
                display: flex;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.04em;
                line-height: 1.35;
                margin: 0 0 10px;
                text-transform: uppercase;
            }

            .laca-admin-dock__group-title .dashicons,
            .laca-admin-dock__group-count {
                display: none;
            }

            .laca-admin-dock__items {
                display: grid;
                gap: 6px;
            }

            .laca-admin-dock__item {
                border: 1px solid transparent;
                border-radius: 14px;
                color: #334155;
                display: block;
                font-size: 13px;
                font-weight: 600;
                line-height: 1.4;
                padding: 11px 14px;
                position: relative;
                text-decoration: none;
                transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
            }

            .laca-admin-dock__item:hover,
            .laca-admin-dock__item:focus {
                background: #ffffff;
                border-color: #dbe1ea;
                color: #0f172a;
                outline: none;
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            }

            .laca-admin-dock__item.is-active {
                background: rgba(37, 99, 235, 0.07);
                border-color: rgba(37, 99, 235, 0.16);
                color: #0f172a;
                font-weight: 700;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35);
            }

            .laca-admin-dock__item.is-active::before {
                background: #2563eb;
                border-radius: 999px;
                bottom: 10px;
                content: "";
                left: 8px;
                position: absolute;
                top: 10px;
                width: 3px;
            }

            @media (max-width: 782px) {
                body.laca-admin-dock-active #wpcontent,
                body.laca-admin-dock-active.folded #wpcontent,
                body.laca-admin-dock-active:not(.folded) #wpcontent {
                    padding-left: 10px;
                }

                .laca-admin-dock {
                    border: 1px solid #dbe1ea;
                    border-radius: 16px;
                    bottom: auto;
                    left: auto;
                    margin: 12px 10px 18px;
                    position: relative;
                    top: auto;
                    width: auto;
                }

                body.folded .laca-admin-dock {
                    left: auto;
                }
            }
        </style>
        <?php
    }

    public function renderNavigationDock(): void
    {
        if (!$this->isLacaAdminRequest() || $this->navigationGroups === []) {
            return;
        }

        $currentSlug = $this->getCurrentPageSlug();
        ?>
        <nav class="laca-admin-dock" aria-label="<?php echo esc_attr__('Laca Admin', 'laca'); ?>">
            <?php foreach ($this->navigationGroups as $group): ?>
                <section class="laca-admin-dock__group">
                    <h2 class="laca-admin-dock__group-title">
                        <span class="dashicons <?php echo esc_attr($group['icon']); ?>" aria-hidden="true"></span>
                        <span><?php echo esc_html($group['label']); ?></span>
                        <span class="laca-admin-dock__group-count"><?php echo esc_html((string) count($group['items'])); ?></span>
                    </h2>
                    <div class="laca-admin-dock__items">
                        <?php foreach ($group['items'] as $item): ?>
                            <a class="laca-admin-dock__item<?php echo $currentSlug === $item['slug'] ? ' is-active' : ''; ?>"
                               href="<?php echo esc_url($item['url']); ?>"
                               <?php echo $currentSlug === $item['slug'] ? 'aria-current="page"' : ''; ?>>
                                <?php echo esc_html($item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private function isLacaAdminRequest(): bool
    {
        return $this->getCurrentPageSlug() !== ''
            && $this->isKnownLacaAdminSlug($this->getCurrentPageSlug());
    }

    private function getCurrentPageSlug(): string
    {
        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));

        if ($page === 'lacadev-control-center') {
            $tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'general'));

            foreach (self::THEME_SETTINGS_TABS as $slug => $config) {
                if ($config['tab'] === $tab) {
                    return $slug;
                }
            }

            return 'laca-theme-settings-general';
        }

        if ($page !== 'laca-security') {
            return $page;
        }

        $tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'audit'));

        foreach (self::SECURITY_TABS as $slug => $config) {
            if ($config['tab'] === $tab) {
                return $slug;
            }
        }

        return 'laca-security-audit';
    }

    private function isKnownLacaAdminSlug(string $slug): bool
    {
        if ($slug === self::PARENT_SLUG) {
            return true;
        }

        foreach (self::GROUPS as $group) {
            if (in_array($slug, $group['items'], true)) {
                return true;
            }
        }

        if (isset(self::THEME_SETTINGS_TABS[$slug])) {
            return true;
        }

        global $submenu;
        foreach (($submenu[self::PARENT_SLUG] ?? []) as $item) {
            if ((string) ($item[2] ?? '') === $slug) {
                return true;
            }
        }

        return false;
    }
}
