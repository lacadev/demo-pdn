<?php
namespace App\Features;

/**
 * RoleBasedAdminUx
 *
 * Tailors the WordPress admin UI to each role — hides clutter for
 * non-technical users while keeping everything for editors/admins.
 *
 * Role tiers:
 *   super    — developer / site-owner (full access, no changes)
 *   admin    — site administrator (standard admin minus dev tools)
 *   editor   — content editor (content-focused UI)
 *   author   — author (minimal, post-focused)
 *   viewer   — subscriber / custom readonly role
 *
 * Features:
 *   - Menu items hidden per tier via `lacadev/admin-ux/menu_rules`
 *   - Toolbar (adminbar) nodes hidden per tier
 *   - Dashboard welcome panel hidden for non-supers
 *   - Post-list columns simplified for author/viewer
 *   - Redirect non-editors away from unused pages
 *
 * All rules are filterable so child themes can extend.
 *
 * @package App\Features
 */
class RoleBasedAdminUx
{
    public function init(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu',          [$this, 'applyMenuRules'],   999);
        add_action('wp_before_admin_bar_render', [$this, 'applyToolbarRules']);
        add_action('admin_head',          [$this, 'applyInlineCss']);
        add_action('admin_init',          [$this, 'applyRedirectRules']);

        // Welcome panel: hide for everyone except admins/supers
        if (!$this->isAtLeast('admin')) {
            remove_action('welcome_panel', 'wp_welcome_panel');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Menu rules
    // ─────────────────────────────────────────────────────────────────────

    public function applyMenuRules(): void
    {
        $tier  = $this->getTier();
        $rules = $this->getMenuRules();

        foreach ($rules as $rule) {
            [$minTier, $menuSlug, $subSlug] = array_pad($rule, 3, null);

            // If current user is BELOW the minTier threshold, hide the item
            if ($this->tierLevel($tier) < $this->tierLevel($minTier)) {
                if ($subSlug) {
                    remove_submenu_page($menuSlug, $subSlug);
                } else {
                    remove_menu_page($menuSlug);
                }
            }
        }
    }

    /**
     * Menu hide rules.
     * Format: [min_tier_to_see, menu_slug, sub_slug|null]
     * Users BELOW min_tier will have the item removed.
     */
    private function getMenuRules(): array
    {
        $defaults = [
            // ── Only supers see plugin management ────────────────────────
            ['super',  'plugins.php',               null],
            ['super',  'plugin-install.php',         null],
            ['super',  'plugin-editor.php',          null],
            ['super',  'theme-editor.php',           null],

            // ── Only admins see full Tools menu ──────────────────────────
            ['admin',  'tools.php',                  null],
            ['admin',  'export.php',                 null],
            ['admin',  'import.php',                 null],

            // ── Editors see Users list but not Add New ────────────────────
            ['admin',  'users.php',         'user-new.php'],

            // ── Authors see only their own content (WP handles caps) ─────
            ['editor', 'edit-comments.php',          null],

            // ── Viewers / subscribers see nothing except profile ─────────
            ['author', 'options-general.php',        null],
            ['author', 'themes.php',                 null],
        ];

        return (array) apply_filters('lacadev/admin-ux/menu_rules', $defaults);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Toolbar rules
    // ─────────────────────────────────────────────────────────────────────

    public function applyToolbarRules(): void
    {
        global $wp_admin_bar;

        $tier  = $this->getTier();
        $rules = $this->getToolbarRules();

        foreach ($rules as [$minTier, $nodeId]) {
            if ($this->tierLevel($tier) < $this->tierLevel($minTier)) {
                $wp_admin_bar->remove_node($nodeId);
            }
        }
    }

    private function getToolbarRules(): array
    {
        $defaults = [
            ['super', 'wp-logo'],
            ['admin', 'customize'],
            ['admin', 'updates'],
            ['editor','comments'],
            ['editor','new-post'],
            ['author','site-name'],
        ];

        return (array) apply_filters('lacadev/admin-ux/toolbar_rules', $defaults);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Redirect rules — bounce users from pages they shouldn't access
    // ─────────────────────────────────────────────────────────────────────

    public function applyRedirectRules(): void
    {
        if ($this->isAtLeast('editor')) {
            return; // editors and above have no restrictions
        }

        global $pagenow;

        $restricted = apply_filters('lacadev/admin-ux/restricted_pages', [
            'plugins.php', 'plugin-install.php', 'plugin-editor.php',
            'theme-editor.php', 'tools.php', 'options-general.php',
        ]);

        if (in_array($pagenow, $restricted, true)) {
            wp_safe_redirect(admin_url('index.php'));
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Inline CSS tweaks
    // ─────────────────────────────────────────────────────────────────────

    public function applyInlineCss(): void
    {
        $tier = $this->getTier();

        if ($tier === 'viewer' || $tier === 'author') {
            // Compact, distraction-free admin for low-tier users
            echo '<style>
#adminmenu .wp-menu-separator { display:none; }
#screen-options-link-wrap, #contextual-help-link-wrap { display:none; }
.update-nag, .updated, .notice-warning.notice-alt { display:none; }
</style>' . "\n";
        }

        // Role badge in top toolbar for developer reference
        if (WP_DEBUG && $this->isAtLeast('admin')) {
            $label = esc_html(strtoupper($tier));
            echo "<style>
#wpadminbar::after {
    content: '{$label}';
    position: fixed;
    bottom: 8px;
    right: 8px;
    background: rgba(0,0,0,.55);
    color: #fff;
    font: 700 10px/1 monospace;
    padding: 3px 6px;
    border-radius: 4px;
    pointer-events: none;
    z-index: 99999;
}
</style>\n";
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tier helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Map current user to a tier slug.
     * Child theme can extend via `lacadev/admin-ux/tier` filter.
     */
    private function getTier(?\WP_User $user = null): string
    {
        $user ??= wp_get_current_user();

        if (!$user || !$user->ID) {
            return 'viewer';
        }

        // Developer override: support both the legacy and namespaced filters.
        $superLogins = array_unique(array_filter(array_merge(
            (array) apply_filters('lacadev_super_user_logins', []),
            (array) apply_filters('lacadev/super_user_logins', [])
        )));
        if (in_array($user->user_login, $superLogins, true)) {
            return 'super';
        }

        $roles = $user->roles ?? [];

        $tier = match (true) {
            in_array('administrator', $roles) => 'admin',
            in_array('editor',        $roles) => 'editor',
            in_array('author',        $roles) => 'author',
            in_array('contributor',   $roles) => 'author',
            default                           => 'viewer',
        };

        return (string) apply_filters('lacadev/admin-ux/tier', $tier, $user);
    }

    private function tierLevel(string $tier): int
    {
        return match ($tier) {
            'super'  => 5,
            'admin'  => 4,
            'editor' => 3,
            'author' => 2,
            'viewer' => 1,
            default  => 0,
        };
    }

    private function isAtLeast(string $minTier, ?\WP_User $user = null): bool
    {
        return $this->tierLevel($this->getTier($user)) >= $this->tierLevel($minTier);
    }
}
