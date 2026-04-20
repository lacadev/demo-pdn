<?php
if ( ! defined( 'ABSPATH' ) ) exit;



// ── Appearance attributes ──────────────────────────────────────────────────
$bg_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' ) ? $attributes['bgColor'] : '#0f0f0f';
$bg_opacity   = max( 0, min( 100, intval( $attributes['bgOpacity'] ?? 100 ) ) );
$r = hexdec( substr( $bg_color, 1, 2 ) );
$g = hexdec( substr( $bg_color, 3, 2 ) );
$b = hexdec( substr( $bg_color, 5, 2 ) );
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ( $bg_opacity / 100 ) . ')';
/**
 * Journey Gallery Block — render.php
 *
 * Desktop: CSS Grid 2 cột
 *   - Bước lẻ (1,3,5…) → hàng 1, cột tăng dần → text-trái, ảnh-phải
 *   - Bước chẵn (2,4,6…) → hàng 3, cột tăng dần → ảnh-trái, text-phải
 *   - Timeline (đường ngang + chấm) → hàng 2
 *
 * Mobile: flex column theo thứ tự DOM (1,2,3,4…) zigzag
 *   + đường dọc + chấm bên phải
 */

$heading   = esc_html( $attributes['heading'] ?? '' );
$subheading = esc_html( $attributes['subheading'] ?? '' );
$steps     = is_array( $attributes['steps'] ?? [] ) ? $attributes['steps'] : [];
$steps     = array_values( array_filter( $steps, fn( $s ) => ! empty( $s['title'] ) || ! empty( $s['imageUrl'] ) ) );

$margin_top = max( -200, min( 300, intval( $attributes['marginTop'] ?? 0 ) ) );
$margin_bottom = max( -200, min( 300, intval( $attributes['marginBottom'] ?? 0 ) ) );
$padding_top = max( 0, min( 300, intval( $attributes['paddingTop'] ?? 60 ) ) );
$padding_bottom = max( 0, min( 300, intval( $attributes['paddingBottom'] ?? 55 ) ) );

if ( empty( $steps ) ) return;

$step_count = count( $steps );
$cols       = max( 1, (int) ceil( $step_count / 2 ) ); // số cột grid

$wrapper_attrs = [
    'class' => 'block-journey-gallery',
    'style' => sprintf(
        'background:%1$s;margin-top:%2$dpx;margin-bottom:%3$dpx;padding-top:%4$dpx;padding-bottom:%5$dpx;',
        esc_attr( $bg_rgba ),
        $margin_top,
        $margin_bottom,
        $padding_top,
        $padding_bottom
    ),
];
?>

<section <?php echo get_block_wrapper_attributes( $wrapper_attrs ); ?>>
    <div class="container-fluid">

        <?php if ( $heading ) : ?>
            <div class="block-journey-gallery__header">
                <h2 class="block-journey-gallery__heading"><?php echo $heading; ?></h2>
                <?php if ( $subheading ) : ?>
                    <p class="block-journey-gallery__subheading"><?php echo $subheading; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="block-journey-gallery__track" style="--jg-cols:<?php echo $cols; ?>">

            <?php foreach ( $steps as $i => $step ) :
                $num      = $i + 1;
                $col      = floor( $i / 2 ) + 1;       // cột grid (1-based)
                $row      = ( $i % 2 === 0 ) ? 1 : 3;  // hàng 1 (lẻ) hoặc hàng 3 (chẵn)
                $is_even  = ( $i % 2 !== 0 );           // bước chẵn → đảo chiều
                $title    = esc_html( $step['title'] ?? '' );
                $desc     = esc_html( $step['description'] ?? '' );
                $img      = esc_url( $step['imageUrl'] ?? '' );
                $alt      = esc_attr( $step['imageAlt'] ?? $title );
            ?>
                <article
                    class="block-journey-gallery__step<?php echo $is_even ? ' block-journey-gallery__step--even' : ''; ?>"
                    style="--jg-col:<?php echo $col; ?>;--jg-row:<?php echo $row; ?>"
                >
                    <div class="block-journey-gallery__content">
                        <span class="block-journey-gallery__number"><?php echo $num; ?></span>

                        <div class="block-journey-gallery__text">
                            <?php if ( $title ) : ?>
                                <h3 class="block-journey-gallery__title"><?php echo $title; ?></h3>
                            <?php endif; ?>
                            <?php if ( $desc ) : ?>
                                <p class="block-journey-gallery__desc"><?php echo $desc; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $img ) : ?>
                        <div class="block-journey-gallery__image">
                            <img src="<?php echo $img; ?>" alt="<?php echo $alt; ?>" loading="lazy" />
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <!-- Timeline ngang (desktop) — grid-row:2, chiếm toàn bộ cột -->
            <div class="block-journey-gallery__timeline">
                <div class="block-journey-gallery__timeline-line"></div>
                <?php for ( $d = 0; $d < $step_count; $d++ ) : ?>
                    <span class="block-journey-gallery__timeline-dot"></span>
                <?php endfor; ?>
            </div>

        </div><!-- /.block-journey-gallery__track -->
    </div><!-- /.container -->
</section>
