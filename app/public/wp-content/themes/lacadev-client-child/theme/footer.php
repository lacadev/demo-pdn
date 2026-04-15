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
?>

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