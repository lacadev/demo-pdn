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
 * Team Carousel Block — render.php
 * Swiper centeredSlides, active slide full size, inactive at {inactiveScale}%.
 * Scale effect handled via CSS (.swiper-slide-active selector).
 *
 * @package lacadev-client-child
 */

// ── Sanitize attributes ────────────────────────────────────────────────────
$section_title  = esc_html( $attributes['sectionTitle'] ?? '' );
$slides         = is_array( $attributes['slides'] ?? [] ) ? $attributes['slides'] : [];
$loop           = ! empty( $attributes['loop'] );
$autoplay       = ! empty( $attributes['autoplay'] );
$autoplay_delay = intval( $attributes['autoplayDelay'] ?? 3000 );
$space_between  = intval( $attributes['spaceBetween'] ?? 24 );
$inactive_scale = max( 40, min( 95, intval( $attributes['inactiveScale'] ?? 60 ) ) );
$bg_color       = sanitize_hex_color( $attributes['bgColor'] ?? '' ) ?: '#1a1a1a';
$label_bg       = sanitize_hex_color( $attributes['labelBg'] ?? '' ) ?: '#F5C518';
$label_color    = sanitize_hex_color( $attributes['labelColor'] ?? '' ) ?: '#1a1a1a';

$slides = array_values( array_filter( $slides, fn( $s ) => ! empty( $s['imageUrl'] ) || ! empty( $s['label'] ) ) );

if ( empty( $slides ) ) return;
$slides_count = count( $slides );

// ── Unique ID per instance ─────────────────────────────────────────────────
static $tc_instance = 0;
$tc_instance++;
$swiper_id = 'team-carousel-' . $tc_instance;

// ── Enqueue Swiper ─────────────────────────────────────────────────────────
if ( ! wp_style_is( 'swiper', 'enqueued' ) ) {
    wp_enqueue_style( 'swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11' );
}
if ( ! wp_script_is( 'swiper', 'enqueued' ) ) {
    wp_enqueue_script( 'swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11', true );
}

// Convert inactiveScale % → CSS ratio string for inline style
$scale_ratio_css = round( $inactive_scale / 100, 2 );
?>

<section <?php echo get_block_wrapper_attributes( [
    'class' => 'block-team-carousel',
    'style' => 'background:' . esc_attr( $bg_color ) . ';',
] ); ?>>

    <?php if ( $section_title ) : ?>
        <div class="container">
            <h2 class="block-team-carousel__heading"><?php echo $section_title; ?></h2>
        </div>
    <?php endif; ?>

    <div class="swiper block-team-carousel__swiper" id="<?php echo esc_attr( $swiper_id ); ?>">
        <div class="swiper-wrapper">
            <?php foreach ( $slides as $slide ) :
                $img_url = esc_url( $slide['imageUrl'] ?? '' );
                $img_alt = esc_attr( $slide['imageAlt'] ?? $slide['label'] ?? '' );
                $label   = esc_html( $slide['label'] ?? '' );
            ?>
                <div class="swiper-slide block-team-carousel__slide">
                    <div class="block-team-carousel__slide-inner">
                        <?php if ( $img_url ) : ?>
                            <img
                                src="<?php echo $img_url; ?>"
                                alt="<?php echo $img_alt; ?>"
                                loading="lazy"
                                class="block-team-carousel__img"
                            />
                        <?php else : ?>
                            <div class="block-team-carousel__no-image"></div>
                        <?php endif; ?>

                        <?php if ( $label ) : ?>
                            <div
                                class="block-team-carousel__label"
                                style="background:<?php echo esc_attr( $label_bg ); ?>;color:<?php echo esc_attr( $label_color ); ?>;"
                            >
                                <?php echo $label; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Trước', 'laca' ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>
        <button type="button" class="swiper-button-next" aria-label="<?php esc_attr_e( 'Tiếp theo', 'laca' ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </button>
    </div>

</section>

<?php
// Inline hardening styles to prevent third-party Swiper/global CSS overrides.
// Scoped by instance id so each block is isolated.
$instance_style = sprintf(
    '#%1$s .swiper-slide{width:clamp(240px,32vw,400px);height:clamp(320px,44vw,540px);flex-shrink:0;}#%1$s .block-team-carousel__slide-inner{transform:scale(var(--tc-inactive-scale,0.62));transition:transform .5s cubic-bezier(.25,.46,.45,.94);}#%1$s .swiper-slide-active .block-team-carousel__slide-inner{transform:scale(1);}',
    $swiper_id
);
wp_add_inline_style( 'swiper', $instance_style );

// ── Inline Swiper init ─────────────────────────────────────────────────────
// Scale effect is handled by CSS .swiper-slide-active selector.
// Use rewind to keep order deterministic without loop clones.
// If inactiveScale differs from CSS default (62%), inject a custom property.
$scale_override = '';
if ( $inactive_scale !== 62 ) {
    $scale_override = sprintf(
        'document.getElementById("%s").style.setProperty("--tc-inactive-scale", "%s");',
        $swiper_id,
        $scale_ratio_css
    );
}

$js = sprintf( '
(function () {
    var swiperInstance = null;
    var resizeTimer = null;
    var desktopMq = window.matchMedia("(min-width: 992px)");
    var lastDesktop = desktopMq.matches;

    function mount_%1$s(forceFirstSlide) {
        if (typeof Swiper === "undefined") {
            setTimeout(function () { mount_%1$s(forceFirstSlide); }, 80);
            return;
        }
        var el = document.getElementById("%2$s");
        if (!el) return;

        // Reset previous instance to avoid broken order after viewport changes.
        if (swiperInstance && typeof swiperInstance.destroy === "function") {
            swiperInstance.destroy(true, true);
        }
        if (el.swiper && typeof el.swiper.destroy === "function") {
            el.swiper.destroy(true, true);
        }

        %3$s
        swiperInstance = new Swiper("#%2$s", {
            slidesPerView: 1.35,
            centeredSlides: true,
            spaceBetween: %4$d,
            loop: false,
            rewind: %5$s,
            watchOverflow: false,
            observer: true,
            observeParents: true,
            speed: 500,
            initialSlide: 0,
            breakpoints: {
                768:  { slidesPerView: 2.2, centeredSlides: true },
                1200: { slidesPerView: 3,   centeredSlides: true }
            },
            %6$s
            navigation: {
                nextEl: "#%2$s .swiper-button-next",
                prevEl: "#%2$s .swiper-button-prev"
            }
        });

        // Always anchor on first slide to keep order deterministic.
        if (forceFirstSlide) {
            swiperInstance.slideTo(0, 0, false);
        }
    }

    function onViewportChange_%1$s() {
        var isDesktop = desktopMq.matches;
        if (isDesktop !== lastDesktop) {
            lastDesktop = isDesktop;
            mount_%1$s(true);
            return;
        }
        if (swiperInstance && typeof swiperInstance.update === "function") {
            swiperInstance.update();
        }
    }

    if (desktopMq.addEventListener) {
        desktopMq.addEventListener("change", onViewportChange_%1$s);
    } else if (desktopMq.addListener) {
        desktopMq.addListener(onViewportChange_%1$s);
    }

    window.addEventListener("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(onViewportChange_%1$s, 120);
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { mount_%1$s(true); });
    } else {
        mount_%1$s(true);
    }
})();',
    $tc_instance,                                  // %1$s — unique fn suffix
    $swiper_id,                                    // %2$s — swiper id
    $scale_override,                               // %3$s — optional CSS var override
    $space_between,                                // %4$d — spaceBetween
    $loop ? 'true' : 'false',                      // %5$s — rewind
    $autoplay                                      // %6$s — optional autoplay
        ? sprintf( 'autoplay:{delay:%d,disableOnInteraction:false,pauseOnMouseEnter:true},', $autoplay_delay )
        : ''
);
wp_add_inline_script( 'swiper', $js );
