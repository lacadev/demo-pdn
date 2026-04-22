<?php

/**
 * Theme footer partial.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */
?>
<!-- footer -->
<?php
$company = getOption('company');
$logo_footer = getOption('logo_footer');
$logo_footer_url = wp_get_attachment_image_url($logo_footer, 'full');
$footer_contact_heading = __('NHẬN TƯ VẤN NGAY', 'laca');
$footer_contact_button_text = __('GỬI YÊU CẦU', 'laca');
$footer_contact_budget_raw = trim((string) carbon_get_theme_option('footer_contact_budget_options'));
$footer_contact_budget_raw_i18n = trim((string) getOption('footer_contact_budget_options'));
$footer_contact_budget_source = $footer_contact_budget_raw !== '' ? $footer_contact_budget_raw : $footer_contact_budget_raw_i18n;
$footer_contact_budget_options = $footer_contact_budget_source !== ''
  ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $footer_contact_budget_source))))
  : [
    __('Dưới 1 tỷ', 'laca'),
    __('1 - 3 tỷ', 'laca'),
    __('3 - 5 tỷ', 'laca'),
    __('5 - 10 tỷ', 'laca'),
    __('Trên 10 tỷ', 'laca'),
  ];
$footer_contact_image_id = (int) carbon_get_theme_option('footer_contact_image');
if (!$footer_contact_image_id) {
  $footer_contact_image_id = (int) getOption('footer_contact_image');
}
$footer_contact_image_url = $footer_contact_image_id ? wp_get_attachment_image_url($footer_contact_image_id, 'large') : '';
$footer_contact_image_alt = $footer_contact_image_id ? get_post_meta($footer_contact_image_id, '_wp_attachment_image_alt', true) : '';
?>

<!-- block contact -->
<section class="footer-contact-form">
  <div class="container">
    <div class="bcf__inner">
      <div class="bcf__left">
        <?php if ($footer_contact_heading): ?>
          <h2 class="bcf__heading"><?php echo esc_html($footer_contact_heading); ?></h2>
        <?php endif; ?>

        <form
          class="bcf__form"
          method="POST"
          action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
          novalidate
          data-bcf-form
        >
          <?php wp_nonce_field('laca_footer_contact_nonce', 'nonce'); ?>
          <input type="hidden" name="action" value="laca_footer_contact_submit">

          <div class="bcf__field">
            <input
              type="text"
              name="tf_address"
              class="bcf__input"
              placeholder="<?php esc_attr_e('Địa chỉ xây dựng', 'laca'); ?>"
              required
            >
          </div>

          <div class="bcf__field">
            <input
              type="text"
              name="tf_scale"
              class="bcf__input"
              placeholder="<?php esc_attr_e('Quy mô xây dựng', 'laca'); ?>"
              required
            >
          </div>

          <div class="bcf__field">
            <select name="tf_budget" class="bcf__select" required>
              <option value=""><?php esc_html_e('Ngân sách', 'laca'); ?></option>
              <?php foreach ($footer_contact_budget_options as $option): ?>
                <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="bcf__field">
            <input
              type="text"
              name="tf_name"
              class="bcf__input"
              placeholder="<?php esc_attr_e('Họ và tên', 'laca'); ?>"
              required
            >
          </div>

          <div class="bcf__field">
            <input
              type="tel"
              name="tf_phone"
              class="bcf__input"
              placeholder="<?php esc_attr_e('Số điện thoại liên hệ', 'laca'); ?>"
              required
            >
          </div>

          <div class="bcf__submit-wrap">
            <button type="submit" class="bcf__btn">
              <span class="bcf__btn-text"><?php echo esc_html($footer_contact_button_text); ?></span>
              <span class="bcf__btn-loader" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.42" stroke-dashoffset="10"></circle>
                </svg>
              </span>
            </button>
          </div>

          <div class="bcf__msg" role="alert" hidden></div>
        </form>
      </div>

      <div class="bcf__right">
        <?php if ($footer_contact_image_url): ?>
          <div class="bcf__img-wrap">
            <img
              src="<?php echo esc_url($footer_contact_image_url); ?>"
              alt="<?php echo esc_attr($footer_contact_image_alt ?: get_bloginfo('name')); ?>"
              class="bcf__img"
              loading="lazy"
            >
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<footer class="footer" role="contentinfo">
  <div class="footer__main">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__col footer__col--about">
          <a href="<?php echo esc_url(home_url('/')); ?>" class="footer__logo" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> - Trang chủ">
            <?php if ($logo_footer_url): ?>
              <img src="<?php echo esc_url($logo_footer_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" loading="lazy" width="180" height="auto">
            <?php endif; ?>
          </a>
          <?php if ($googlemap = carbon_get_theme_option('googlemap' . currentLanguage())): ?>
            <div class="footer__slogan"><?php echo $googlemap; ?></div>
          <?php endif; ?>
        </div>

        <div class="footer__col footer__col--contact">
          <h3 class="footer__title"><?php echo esc_html($company); ?></h3>
          <ul class="footer__contact-list">
            <?php
            $ft_phones = getOption('phone_numbers');
            if (!empty($ft_phones)):
              ?>
              <li class="footer__contact-item">
                <svg class="footer__contact-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0;">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span><strong class="footer__contact-label">Hotline:</strong>
                  <?php
                  $phone_links = [];
                  foreach ($ft_phones as $p) {
                    if (!empty($p['phone'])) {
                      $phone_links[] = '<a href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $p['phone'])) . '" class="footer__contact-link">' . esc_html($p['phone']) . '</a>';
                    }
                  }
                  echo implode(' - ', $phone_links);
                  ?></span>
              </li>
            <?php endif; ?>

            <?php
            $footer_email = getOption('email');
            if (!empty($footer_email)):
              ?>
              <li class="footer__contact-item">
                <svg class="footer__contact-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0;">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span><strong class="footer__contact-label">Email:</strong> <a href="mailto:<?php echo esc_attr($footer_email); ?>" class="footer__contact-link"><?php echo esc_html($footer_email); ?></a></span>
              </li>
            <?php endif; ?>

            <?php
            $ft_addresses = getOption('address_locations');
            if (!empty($ft_addresses)):
              foreach ($ft_addresses as $addr):
                if (!empty($addr['address'])):
                  ?>
                  <li class="footer__contact-item">
                    <svg class="footer__contact-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0;">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                      <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span><?php if (!empty($addr['branch'])): ?><strong class="footer__contact-label"><?php echo esc_html($addr['branch']); ?>:</strong> <?php endif; ?><?php echo nl2br(esc_html($addr['address'])); ?></span>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</footer>
<!-- footer end -->

</div><!-- barba container end -->
</div>
<!-- container-wrapper end -->


<?php wp_footer(); ?>
</body>

</html>
