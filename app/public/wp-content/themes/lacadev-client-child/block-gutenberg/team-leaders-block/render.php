<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Team Leaders Block — render.php
 * Section "CON NGƯỜI PHÚC ĐẠI NAM" — ảnh cutout, tên, chức vụ, quote.
 *
 * @package lacadev-client-child
 */

$section_title = esc_html( $attributes['sectionTitle'] ?? 'CON NGƯỜI PHÚC ĐẠI NAM' );
$bg_color      = $attributes['backgroundColor'] ?? '#3A3A3A';
$leaders       = $attributes['leaders'] ?? [];
?>

<section <?php echo get_block_wrapper_attributes( [ 'class' => 'block-team-leaders' ] ); ?> style="background-color:<?php echo esc_attr( $bg_color ); ?>;">
    <div class="block-team-leaders__inner">

        <?php if ( $section_title ) : ?>
            <h2 class="block-team-leaders__title"><?php echo $section_title; ?></h2>
        <?php endif; ?>

        <div class="block-team-leaders__grid">
            <?php foreach ( $leaders as $leader ) : ?>
                <div class="block-team-leaders__card">

                    <figure class="block-team-leaders__figure">
                        <?php if ( ! empty( $leader['imageUrl'] ) ) : ?>
                            <img
                                src="<?php echo esc_url( $leader['imageUrl'] ); ?>"
                                alt="<?php echo esc_attr( $leader['name'] ?? '' ); ?>"
                                loading="lazy"
                            />
                        <?php else : ?>
                            <div class="block-team-leaders__no-image"></div>
                        <?php endif; ?>
                    </figure>

                    <div class="block-team-leaders__info">
                        <div class="block-team-leaders__name-wrap">
                            <span class="block-team-leaders__prefix"><?php echo esc_html( $leader['prefix'] ?? '' ); ?></span>
                            <strong class="block-team-leaders__name"><?php echo esc_html( $leader['name'] ?? '' ); ?></strong>
                        </div>
                        <div class="block-team-leaders__badge"><?php echo esc_html( $leader['position'] ?? '' ); ?></div>
                    </div>

                    <?php if ( ! empty( $leader['quote'] ) ) : ?>
                        <blockquote class="block-team-leaders__quote">
                            "<?php echo esc_html( $leader['quote'] ); ?>"
                        </blockquote>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
