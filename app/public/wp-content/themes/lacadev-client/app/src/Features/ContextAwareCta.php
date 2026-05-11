<?php
namespace App\Features;

/**
 * Context-aware Sticky CTA
 *
 * Renders a floating CTA bar whose message and action adapt to the current
 * page context (post, service page, contact, blog archive, etc.).
 *
 * Configuration via Theme Options (Carbon Fields) or wp_options fallback.
 *
 * Options (wp_options key → default):
 *   laca_cta_enabled         → '1'
 *   laca_cta_hide_logged_in  → '0'
 *   laca_cta_show_desktop    → '1'
 *   laca_cta_show_mobile     → '1'
 *   laca_cta_color_*         → per-context CTA background color
 *
 * Per-context rules (filter-based, see below):
 *   lacadev/cta/rules  → array of rule definitions
 *
 * Rule definition:
 *   [
 *     'condition' => callable,     // Return true when this rule applies
 *     'label'     => string,       // Button text
 *     'url'       => string,       // Button href (empty = scroll to #contact)
 *     'icon'      => string,       // SVG or dashicon class (optional)
 *     'theme'     => string,       // CSS modifier class (optional)
 *     'color'     => string,       // CTA background hex color (optional)
 *   ]
 *
 * @package App\Features
 */
class ContextAwareCta
{
    const COOKIE_KEY      = 'laca_cta_dismissed';
    const COOKIE_DURATION = 86400; // 24 hours

    public function init(): void
    {
        add_action('wp_footer',           [$this, 'render'], 100);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue']);
        add_action('wp_ajax_laca_dismiss_cta',        [$this, 'dismissAjax']);
        add_action('wp_ajax_nopriv_laca_dismiss_cta', [$this, 'dismissAjax']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Dismiss handler
    // ─────────────────────────────────────────────────────────────────────

    public function dismissAjax(): void
    {
        check_ajax_referer('laca_cta_nonce', 'nonce');
        setcookie(self::COOKIE_KEY, '1', time() + self::COOKIE_DURATION, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        wp_send_json_success();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────────

    public function render(): void
    {
        if (!get_option('laca_cta_enabled', '1')) {
            return;
        }

        $showDesktop = get_option('laca_cta_show_desktop', '1') === '1';
        $showMobile = get_option('laca_cta_show_mobile', '1') === '1';
        if (!$showDesktop && !$showMobile) {
            return;
        }

        // Respect dismiss cookie (set by JS)
        if (!empty($_COOKIE[self::COOKIE_KEY])) {
            return;
        }

        // Optionally hide for logged-in users
        if (get_option('laca_cta_hide_logged_in', '0') && is_user_logged_in()) {
            return;
        }

        $rule = $this->matchRule();
        if (!$rule) {
            return;
        }

        $label = esc_html($rule['label']);
        $url   = !empty($rule['url']) ? esc_url($rule['url']) : '#contact';
        $icon  = $rule['icon'] ?? '';
        $theme = !empty($rule['theme']) ? ' laca-cta--' . sanitize_html_class($rule['theme']) : '';
        $visibility = (!$showDesktop ? ' laca-cta--hide-desktop' : '') . (!$showMobile ? ' laca-cta--hide-mobile' : '');
        $color = sanitize_hex_color($rule['color'] ?? '');
        $style = $color ? ' style="--laca-cta-bg: ' . esc_attr($color) . ';"' : '';
        $nonce = wp_create_nonce('laca_cta_nonce');
        ?>
        <div class="laca-cta<?php echo esc_attr($theme . $visibility); ?>" role="complementary" aria-label="<?php echo $label; ?>" data-nonce="<?php echo esc_attr($nonce); ?>"<?php echo $style; ?>>
            <a href="<?php echo $url; ?>" class="laca-cta__btn">
                <?php if ($icon): ?><span class="laca-cta__icon" aria-hidden="true"><?php echo $icon; ?></span><?php endif; ?>
                <span><?php echo $label; ?></span>
            </a>
            <button class="laca-cta__close" type="button" aria-label="Đóng">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Rule matching
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns the first matching rule, or null.
     *
     * @return array{label:string, url:string, icon:string, theme:string, color?:string}|null
     */
    private function matchRule(): ?array
    {
        /** @var array<array{condition:callable,label:string,url:string,icon:string,theme:string,color?:string}> $rules */
        $rules = apply_filters('lacadev/cta/rules', $this->defaultRules());

        foreach ($rules as $rule) {
            if (is_callable($rule['condition'] ?? null) && ($rule['condition'])()) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Default context rules (lowest to highest specificity — first match wins).
     */
    private function defaultRules(): array
    {
        $contactPage = get_option('laca_cta_contact_url', home_url('/lien-he/'));

        return apply_filters('lacadev/cta/default_rules', [

            // Blog article → invite to contact
            [
                'condition' => fn() => is_single() && get_post_type() === 'post',
                'label'     => get_option('laca_cta_blog_label', 'Nhận tư vấn miễn phí →'),
                'url'       => $contactPage,
                'icon'      => '',
                'theme'     => 'blog',
                'color'     => get_option('laca_cta_color_blog', '#1a6b8a'),
            ],

            // Service / landing page → start project
            [
                'condition' => fn() => is_page() && !is_front_page(),
                'label'     => get_option('laca_cta_page_label', 'Bắt đầu dự án →'),
                'url'       => $contactPage,
                'icon'      => '',
                'theme'     => 'page',
                'color'     => get_option('laca_cta_color_page', '#2563eb'),
            ],

            // Product (WooCommerce) → view in cart
            [
                'condition' => fn() => function_exists('is_product') && is_product(),
                'label'     => get_option('laca_cta_product_label', 'Mua ngay →'),
                'url'       => function_exists('wc_get_cart_url') ? wc_get_cart_url() : $contactPage,
                'icon'      => '',
                'theme'     => 'product',
                'color'     => get_option('laca_cta_color_product', '#16a34a'),
            ],

            // Front page → scroll to contact section
            [
                'condition' => fn() => is_front_page(),
                'label'     => get_option('laca_cta_home_label', 'Liên hệ ngay →'),
                'url'       => $contactPage,
                'icon'      => '',
                'theme'     => 'home',
                'color'     => get_option('laca_cta_color_home', '#2563eb'),
            ],

            // Archive / search — general fallback
            [
                'condition' => fn() => is_archive() || is_search(),
                'label'     => get_option('laca_cta_archive_label', 'Xem tất cả dịch vụ →'),
                'url'       => get_option('laca_cta_services_url', home_url('/dich-vu/')),
                'icon'      => '',
                'theme'     => 'archive',
                'color'     => get_option('laca_cta_color_archive', '#2563eb'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────

    public function enqueue(): void
    {
        // Inline CSS — tiny, no separate file needed
        $css = $this->inlineCss();
        if ($css) {
            wp_add_inline_style(\App\Contracts\AssetHandles::THEME_CSS, $css);
        }
        // JS: dismiss logic is inline (no separate file)
        add_action('wp_footer', [$this, 'inlineJs'], 99);
    }

    public function inlineJs(): void
    {
        if (empty($_COOKIE[self::COOKIE_KEY])):
        ?>
        <script<?php echo defined('LACA_CSP_NONCE') ? ' nonce="' . esc_attr(LACA_CSP_NONCE) . '"' : ''; ?>>
        (function(){
            var bar = document.querySelector('.laca-cta');
            if (!bar) return;
            var btn = bar.querySelector('.laca-cta__close');
            if (btn) {
                btn.addEventListener('click', function() {
                    bar.classList.add('laca-cta--hidden');
                    // Persist dismiss via AJAX
                    var nonce = bar.dataset.nonce || '';
                    fetch(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=laca_dismiss_cta&nonce=' + encodeURIComponent(nonce)
                    });
                });
            }
            // Reveal after slight scroll (not on initial load)
            var revealed = false;
            window.addEventListener('scroll', function() {
                if (!revealed && window.scrollY > 300) {
                    bar.classList.add('laca-cta--visible');
                    revealed = true;
                }
            }, {passive: true});
        }());
        </script>
        <?php
        endif;
    }

    private function inlineCss(): string
    {
        return '
.laca-cta {
    position: fixed;
    bottom: 0;
    left: 0; right: 0;
    z-index: 9980;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 0.85rem 1.5rem;
    background: var(--laca-cta-bg, var(--primary-color, #2ea2cc));
    color: #fff;
    box-shadow: 0 -4px 20px rgba(0,0,0,.12);
    transform: translateY(100%);
    transition: transform .35s cubic-bezier(.4,0,.2,1);
    will-change: transform;
}
.laca-cta--visible { transform: translateY(0); }
.laca-cta--hidden  { transform: translateY(100%) !important; pointer-events: none; }
.laca-cta__btn {
    color: #fff;
    font-weight: 700;
    font-size: .95rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: .4rem;
}
.laca-cta__btn:hover { opacity: .85; }
.laca-cta__close {
    background: none;
    border: none;
    cursor: pointer;
    color: rgba(255,255,255,.75);
    padding: .25rem;
    line-height: 1;
    margin-left: auto;
}
.laca-cta__close:hover { color: #fff; }
.laca-cta--blog    { background: var(--laca-cta-bg, var(--secondary-color, #1a6b8a)); }
.laca-cta--product { background: var(--laca-cta-bg, #16a34a); }
@media (min-width: 783px) {
    .laca-cta--hide-desktop { display: none !important; }
}
@media (max-width: 782px) {
    .laca-cta {
        align-items: stretch;
        gap: .75rem;
        padding: .75rem 1rem;
    }
    .laca-cta__btn {
        flex: 1;
        font-size: .9rem;
        justify-content: center;
        min-width: 0;
        text-align: center;
    }
    .laca-cta__btn span:last-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .laca-cta--hide-mobile { display: none !important; }
}
';
    }
}
