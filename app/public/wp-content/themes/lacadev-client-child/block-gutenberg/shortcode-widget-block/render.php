<?php
if (!defined('ABSPATH'))
    exit;

$heading = esc_html($attributes['heading'] ?? '');
$shortcode1 = trim($attributes['shortcode1'] ?? '');
$shortcode2 = trim($attributes['shortcode2'] ?? '');

$is_valid = fn($sc) => preg_match('/^\[[\w\-]/', $sc);
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'block-shortcode-widget']); ?>>
    <div class="container">
        <?php if ($heading): ?>
            <div class="block-shortcode-widget__header">
                <h2 class="block-shortcode-widget__heading"><?php echo $heading; ?></h2>
            </div>
        <?php endif; ?>

        <div class="block-shortcode-widget__cols">
            <?php if ($is_valid($shortcode1)): ?>
                <div class="block-shortcode-widget__col">
                    <?php echo do_shortcode($shortcode1); ?>
                </div>
            <?php endif; ?>

            <?php if ($is_valid($shortcode2)): ?>
                <div class="block-shortcode-widget__col">
                    <?php echo do_shortcode($shortcode2); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>