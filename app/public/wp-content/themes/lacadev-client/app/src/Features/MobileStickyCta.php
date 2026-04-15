<?php

namespace App\Features;

/**
 * MobileStickyCta
 *
 * Thanh CTA cố định ở bottom màn hình trên mobile.
 * Hiển thị 3 nút: Gọi ngay, Zalo, Nhận báo giá.
 *
 * Số điện thoại lấy từ Carbon Fields option: phone_number
 * Zalo lấy từ option: zalo
 * Nút "Báo giá" link đến Contact page hoặc custom URL.
 *
 * Ẩn khi user scroll lên (UX-friendly).
 * Chỉ hiển thị trên mobile (< 768px) qua CSS.
 */
class MobileStickyCta
{
    public function init(): void
    {
        add_action('wp_footer', [$this, 'render'], 20);
    }

    public function render(): void
    {
        if (is_admin()) {
            return;
        }

        $phone   = $this->getOption('phone_number');
        $zalo    = $this->getOption('zalo');
        $contact = get_permalink(get_option('laca_cta_contact_page_id')) ?: home_url('/lien-he/');

        // Nothing to show if no phone
        if (!$phone && !$zalo && !$contact) {
            return;
        }

        $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
        ?>
        <div id="laca-sticky-cta" class="laca-sticky-cta" aria-label="Liên hệ nhanh">
            <?php if ($phone): ?>
            <a href="tel:<?php echo esc_attr($phoneClean); ?>" class="laca-sticky-cta__btn laca-sticky-cta__btn--phone" aria-label="Gọi ngay">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.0 1.18 2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14z"/>
                </svg>
                <span>Gọi ngay</span>
            </a>
            <?php endif; ?>

            <?php if ($zalo): ?>
            <a href="https://zalo.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $zalo)); ?>" target="_blank" rel="noopener" class="laca-sticky-cta__btn laca-sticky-cta__btn--zalo" aria-label="Chat Zalo">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.477 2 2 6.477 2 12c0 2.19.71 4.22 1.92 5.88L2.5 21.5l3.74-1.38A9.97 9.97 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm-1.5 5h3v1.5h-3V7zm-2 3h7v1.5H9.75v3H8.5v-3H7V10zm5.25 4.5H9v-1.5h3.75v1.5z"/>
                </svg>
                <span>Zalo</span>
            </a>
            <?php endif; ?>

            <a href="<?php echo esc_url($contact); ?>" class="laca-sticky-cta__btn laca-sticky-cta__btn--quote">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                <span>Báo giá</span>
            </a>
        </div>

        <style>
        .laca-sticky-cta {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 9990;
            background: #fff;
            box-shadow: 0 -2px 12px rgba(0,0,0,.12);
            padding: 8px 12px env(safe-area-inset-bottom, 8px);
            flex-direction: row;
            gap: 8px;
            transition: transform .3s ease;
        }
        @media (max-width: 768px) {
            .laca-sticky-cta { display: flex; }
            body { padding-bottom: 72px; }
        }
        .laca-sticky-cta.laca-sticky-cta--hidden { transform: translateY(100%); }
        .laca-sticky-cta__btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 6px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: opacity .15s;
        }
        .laca-sticky-cta__btn:active { opacity: .75; }
        .laca-sticky-cta__btn--phone { background: var(--primary-color,#2271b1); color: #fff; }
        .laca-sticky-cta__btn--zalo  { background: #0068ff; color: #fff; }
        .laca-sticky-cta__btn--quote { background: #f0f6fc; color: var(--primary-color,#2271b1); border: 1.5px solid var(--primary-color,#2271b1); }
        [data-theme="dark"] .laca-sticky-cta { background: #1a1a1a; box-shadow: 0 -2px 12px rgba(0,0,0,.4); }
        [data-theme="dark"] .laca-sticky-cta__btn--quote { background: #252525; }
        </style>

        <script>
        (function() {
            const bar = document.getElementById('laca-sticky-cta');
            if (!bar) return;
            let lastY = window.scrollY;
            window.addEventListener('scroll', function() {
                const y = window.scrollY;
                // Hide when scrolling up, show when scrolling down or at top
                if (y < lastY || y < 80) {
                    bar.classList.remove('laca-sticky-cta--hidden');
                } else {
                    bar.classList.add('laca-sticky-cta--hidden');
                }
                lastY = y;
            }, { passive: true });
        })();
        </script>
        <?php
    }

    private function getOption(string $key): string
    {
        if (function_exists('carbon_get_theme_option')) {
            return (string) carbon_get_theme_option($key);
        }
        return '';
    }
}
