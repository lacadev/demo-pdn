<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Projects Slider Block — render.php
 * Swiper fullwidth, centeredSlides, autoplay — clone UI ancuong.com.
 *
 * @package lacadev-client
 */

// ── Sanitize attributes ────────────────────────────────────────────────────
$attr          = $attributes;
$section_title = esc_html( $attr['sectionTitle'] ?? 'Dự Án Sử Dụng Sản Phẩm' );
$cta_text      = esc_html( $attr['ctaText']      ?? 'Xem Thêm' );
$heading_color = sanitize_hex_color( $attr['headingColor'] ?? '' );

$post_type      = sanitize_key( $attr['postType']      ?? 'post' );
$taxonomy       = sanitize_key( $attr['taxonomy']      ?? '' );
$selected_terms = array_map( 'intval', $attr['selectedTerms'] ?? [] );
$mode           = $attr['mode'] ?? 'auto';
$posts_count    = intval( $attr['postsCount']  ?? 6 );
$order_by       = sanitize_key( $attr['orderBy']       ?? 'date' );
$order          = in_array( strtoupper( $attr['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true )
    ? strtoupper( $attr['order'] )
    : 'DESC';
$selected_posts = array_map( 'intval', $attr['selectedPosts'] ?? [] );

// ── Appearance attributes ──────────────────────────────────────────────────
$bg_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attr['bgColor'] ?? '' )
    ? $attr['bgColor']
    : '#0f0f0f';
$bg_opacity   = max( 0, min( 100, intval( $attr['bgOpacity'] ?? 100 ) ) );
$pause_hover  = ! empty( $attr['pauseOnHover'] );

// Convert hex + opacity to rgba for inline style
$r = hexdec( substr( $bg_color, 1, 2 ) );
$g = hexdec( substr( $bg_color, 3, 2 ) );
$b = hexdec( substr( $bg_color, 5, 2 ) );
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ( $bg_opacity / 100 ) . ')';

// ── Enqueue Swiper CSS / JS ────────────────────────────────────────────────
if ( ! wp_style_is( 'swiper', 'enqueued' ) ) {
    wp_enqueue_style(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        [],
        '11'
    );
}
if ( ! wp_script_is( 'swiper', 'enqueued' ) ) {
    wp_enqueue_script(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        '11',
        true
    );
}

// ── Build WP_Query ─────────────────────────────────────────────────────────
if ( $mode === 'manual' && ! empty( $selected_posts ) ) {
    $query_args = [
        'post_type'           => $post_type,
        'post__in'            => $selected_posts,
        'orderby'             => 'post__in',
        'posts_per_page'      => count( $selected_posts ),
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
    ];
} else {
    $query_args = [
        'post_type'           => $post_type,
        'posts_per_page'      => $posts_count,
        'post_status'         => 'publish',
        'orderby'             => $order_by,
        'order'               => $order,
        'ignore_sticky_posts' => true,
    ];
    if ( $taxonomy && ! empty( $selected_terms ) ) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $selected_terms,
            ],
        ];
    }
}

$query = new WP_Query( $query_args );

// ── Unique ID per instance ─────────────────────────────────────────────────
static $instance = 0;
$instance++;
$swiper_id = 'projects-slider-' . $instance;
?>

<section <?php echo get_block_wrapper_attributes( [ 'class' => 'block-projects-slider', 'style' => 'background:' . esc_attr( $bg_rgba ) . ';' ] ); ?>>

    <?php if ( $section_title ) : ?>
        <div class="block-projects-slider__header">
            <h2 class="block-projects-slider__heading"<?php echo $heading_color ? ' style="color:' . esc_attr( $heading_color ) . ';"' : ''; ?>><?php echo $section_title; ?></h2>
        </div>
    <?php endif; ?>

    <?php if ( $query->have_posts() ) : ?>

        <div class="swiper block-projects-slider__swiper" id="<?php echo esc_attr( $swiper_id ); ?>">
            <div class="swiper-wrapper">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php
                    $post_id    = get_the_ID();
                    $post_link  = esc_url( get_permalink() );
                    $post_title = get_the_title();
                    $thumb_url  = get_the_post_thumbnail_url( $post_id, 'large' );
                    $thumb_alt  = esc_attr(
                        get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true )
                        ?: $post_title
                    );

                    // Taxonomy label
                    $cat_name = '';
                    if ( $taxonomy ) {
                        $terms_list = get_the_terms( $post_id, $taxonomy );
                        if ( $terms_list && ! is_wp_error( $terms_list ) ) {
                            $cat_name = esc_html( $terms_list[0]->name );
                        }
                    } else {
                        $cats = get_the_category( $post_id );
                        if ( $cats ) {
                            $cat_name = esc_html( $cats[0]->name );
                        }
                    }
                    ?>
                    <div class="swiper-slide block-projects-slider__slide">
                        <a href="<?php echo $post_link; ?>"
                           class="block-projects-slider__image-link"
                           aria-label="<?php echo esc_attr( $post_title ); ?>">

                            <?php if ( $thumb_url ) : ?>
                                <img
                                    src="<?php echo esc_url( $thumb_url ); ?>"
                                    alt="<?php echo $thumb_alt; ?>"
                                    loading="lazy"
                                    class="block-projects-slider__img"
                                />
                            <?php else : ?>
                                <div class="block-projects-slider__no-image" aria-hidden="true"></div>
                            <?php endif; ?>

                            <div class="block-projects-slider__overlay">
                                <?php if ( $cat_name ) : ?>
                                    <span class="block-projects-slider__cat"><?php echo $cat_name; ?></span>
                                <?php endif; ?>
                                <h3 class="block-projects-slider__title">
                                    <?php echo esc_html( $post_title ); ?>
                                </h3>
                                <span class="block-projects-slider__cta" aria-hidden="true">
                                    <?php echo $cta_text; ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                        <polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </span>
                            </div>

                        </a>
                    </div>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            </div>

            <button class="swiper-button-prev block-projects-slider__nav block-projects-slider__nav--prev"
                    aria-label="<?php esc_attr_e( 'Dự án trước', 'laca' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
            <button class="swiper-button-next block-projects-slider__nav block-projects-slider__nav--next"
                    aria-label="<?php esc_attr_e( 'Dự án tiếp theo', 'laca' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
        </div>

    <?php else : ?>
        <p class="block-projects-slider__empty">
            <?php esc_html_e( 'Chưa có dự án nào.', 'laca' ); ?>
        </p>
    <?php endif; ?>

</section>

<?php
// ── Inline Swiper init ─────────────────────────────────────────────────────
$js = sprintf( '
(function () {
    function init_%1$s() {
        if (typeof Swiper === "undefined") { setTimeout(init_%1$s, 80); return; }
        var swiper = new Swiper("#%2$s", {
            slidesPerView: 1.3,
            centeredSlides: true,
            spaceBetween: 5,
            loop: true,
            speed: 6000,
            autoplay: {
                delay: 0,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            navigation: {
                nextEl: "#%2$s .swiper-button-next",
                prevEl: "#%2$s .swiper-button-prev"
            },
            breakpoints: {
                600: { slidesPerView: 1.8},
                900: { slidesPerView: 2.4},
                1200: { slidesPerView: 2.8}
            }
        });
        if (%3$s) {
            var section = document.getElementById("%2$s").closest("section");
            if (section) {
                section.addEventListener("mouseenter", function () { swiper.autoplay.stop(); });
                section.addEventListener("mouseleave", function () { swiper.autoplay.start(); });
            }
        }
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init_%1$s);
    } else {
        init_%1$s();
    }
})();',
    $instance,
    $swiper_id,
    $pause_hover ? 'true' : 'false'
);
wp_add_inline_script( 'swiper', $js );
