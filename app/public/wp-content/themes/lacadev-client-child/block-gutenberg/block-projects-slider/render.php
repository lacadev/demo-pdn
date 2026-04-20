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

$show_popup      = ! empty( $attr['showPopupForm'] );
$popup_budget    = is_array( $attr['popupBudgetOptions'] ?? [] ) ? $attr['popupBudgetOptions'] : [];
$popup_btn_text  = esc_html( $attr['popupButtonText'] ?? 'GỬI YÊU CẦU' );

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

$section_extra_attrs = 'class="block-projects-slider" style="background:' . esc_attr( $bg_rgba ) . ';"';
if ( $show_popup ) {
    $popup_id = 'pslider-popup-' . $instance;
    $section_extra_attrs .= ' data-popup-id="' . esc_attr( $popup_id ) . '"';
}
?>

<section <?php echo get_block_wrapper_attributes(); ?> <?php echo $section_extra_attrs; ?>>

    <div class="container">
        <?php if ( $section_title ) : ?>
            <div class="block-projects-slider__header">
                <h2 class="block-projects-slider__heading"<?php echo $heading_color ? ' style="color:' . esc_attr( $heading_color ) . ';"' : ''; ?>><?php echo $section_title; ?></h2>
            </div>
        <?php endif; ?>
    </div>

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
        var SLOW = 6000, FAST = 400;
        var swiper = new Swiper("#%2$s", {
            slidesPerView: 1.3,
            centeredSlides: true,
            spaceBetween: 5,
            loop: true,
            speed: SLOW,
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
        /* Nav: interrupt mid-animation, slide fast, restore slow autoplay */
        var el = document.getElementById("%2$s");
        var hovering = false;
        function navGo(dir) {
            swiper.autoplay.stop();
            swiper.animating = false;
            var w = swiper.wrapperEl;
            var cur = getComputedStyle(w).transform;
            w.style.transitionDuration = "0ms";
            w.style.transform = cur;
            w.offsetHeight;
            swiper.params.speed = FAST;
            if (dir === "next") swiper.slideNext(FAST);
            else swiper.slidePrev(FAST);
        }
        el.querySelector(".swiper-button-next").addEventListener("mousedown", function(e){ e.stopPropagation(); navGo("next"); }, true);
        el.querySelector(".swiper-button-prev").addEventListener("mousedown", function(e){ e.stopPropagation(); navGo("prev"); }, true);
        swiper.on("transitionEnd", function(){
            if (swiper.params.speed === FAST) {
                swiper.params.speed = SLOW;
                if (!hovering) swiper.autoplay.start();
            }
        });
        if (%3$s) {
            var section = el.closest("section");
            if (section) {
                section.addEventListener("mouseenter", function () {
                    hovering = true;
                    swiper.autoplay.stop();
                    swiper.animating = false;
                    var w = swiper.wrapperEl;
                    var cur = getComputedStyle(w).transform;
                    w.style.transitionDuration = "0ms";
                    w.style.transform = cur;
                    w.offsetHeight;
                });
                section.addEventListener("mouseleave", function () {
                    hovering = false;
                    swiper.params.speed = SLOW;
                    swiper.autoplay.start();
                });
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

// ── Popup Contact Form (scroll-triggered) ──────────────────────────────────
if ( $show_popup ) :
?>
<div class="pslider-popup" id="<?php echo esc_attr( $popup_id ); ?>" hidden>
    <div class="pslider-popup__backdrop"></div>
    <div class="pslider-popup__panel">
        <button class="pslider-popup__close" aria-label="<?php esc_attr_e( 'Đóng', 'laca' ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <h3 class="pslider-popup__title"><?php esc_html_e( 'Nhận Tư Vấn Ngay', 'laca' ); ?></h3>

        <form class="pslider-popup__form" method="POST"
              action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
              novalidate data-pslider-form>
            <?php wp_nonce_field( 'laca_footer_contact_nonce', 'nonce' ); ?>
            <input type="hidden" name="action" value="laca_footer_contact_submit">

            <div class="pslider-popup__field">
                <input type="text" name="tf_address" class="pslider-popup__input"
                       placeholder="<?php esc_attr_e( 'Địa chỉ xây dựng', 'laca' ); ?>" required>
            </div>
            <div class="pslider-popup__field">
                <input type="text" name="tf_scale" class="pslider-popup__input"
                       placeholder="<?php esc_attr_e( 'Quy mô xây dựng', 'laca' ); ?>" required>
            </div>
            <div class="pslider-popup__field">
                <select name="tf_budget" class="pslider-popup__select" required>
                    <option value=""><?php esc_html_e( 'Ngân sách', 'laca' ); ?></option>
                    <?php foreach ( $popup_budget as $opt ) :
                        if ( empty( $opt ) ) continue; ?>
                        <option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pslider-popup__field">
                <input type="text" name="tf_name" class="pslider-popup__input"
                       placeholder="<?php esc_attr_e( 'Họ và tên', 'laca' ); ?>" required>
            </div>
            <div class="pslider-popup__field">
                <input type="tel" name="tf_phone" class="pslider-popup__input"
                       placeholder="<?php esc_attr_e( 'Số điện thoại liên hệ', 'laca' ); ?>" required>
            </div>

            <button type="submit" class="pslider-popup__btn">
                <span class="pslider-popup__btn-text"><?php echo $popup_btn_text; ?></span>
                <span class="pslider-popup__btn-loader" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.42" stroke-dashoffset="10"/></svg>
                </span>
            </button>

            <div class="pslider-popup__msg" role="alert" hidden></div>
        </form>
    </div>
</div>
<?php
    $popup_js = sprintf( '
(function(){
    var popup = document.getElementById("%1$s");
    if (!popup) return;
    var KEY = "pslider_popup_shown_%2$d";
    var section = document.querySelector(\'[data-popup-id="%1$s"]\');
    if (!section) return;

    /* ── Show / Hide helpers ── */
    function showPopup() {
        popup.removeAttribute("hidden");
        popup.offsetHeight;
        popup.classList.add("pslider-popup--visible");
        document.body.style.overflow = "hidden";
    }
    function hidePopup() {
        popup.classList.remove("pslider-popup--visible");
        document.body.style.overflow = "";
        setTimeout(function(){ popup.setAttribute("hidden",""); }, 350);
    }

    /* ── Close handlers ── */
    popup.querySelector(".pslider-popup__close").addEventListener("click", hidePopup);
    popup.querySelector(".pslider-popup__backdrop").addEventListener("click", hidePopup);

    /* ── IntersectionObserver: trigger once per session, only after user scrolls ── */
    if (sessionStorage.getItem(KEY)) return;
    var observer = new IntersectionObserver(function(entries) {
        if (entries[0].isIntersecting) {
            observer.disconnect();
            sessionStorage.setItem(KEY, "1");
            setTimeout(showPopup, 500);
        }
    }, { threshold: 0.3 });
    /* Wait for first scroll before observing — prevents firing on page load */
    var scrollHandler = function() {
        window.removeEventListener("scroll", scrollHandler);
        if (section) observer.observe(section);
    };
    window.addEventListener("scroll", scrollHandler, { passive: true });

    /* ── Form AJAX submit ── */
    var form = popup.querySelector("[data-pslider-form]");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            var btn = form.querySelector(".pslider-popup__btn");
            var msg = form.querySelector(".pslider-popup__msg");
            btn.classList.add("pslider-popup__btn--loading");
            btn.disabled = true;
            var fd = new FormData(form);
            fetch(form.action, { method: "POST", body: fd, credentials: "same-origin" })
            .then(function(r){ return r.json(); })
            .then(function(d){
                msg.removeAttribute("hidden");
                if (d.success) {
                    msg.textContent = d.data || "Gửi thành công!";
                    msg.className = "pslider-popup__msg pslider-popup__msg--ok";
                    form.reset();
                    setTimeout(hidePopup, 1500);
                } else {
                    msg.textContent = d.data || "Có lỗi, vui lòng thử lại.";
                    msg.className = "pslider-popup__msg pslider-popup__msg--err";
                }
            })
            .catch(function(){
                msg.removeAttribute("hidden");
                msg.textContent = "Lỗi kết nối.";
                msg.className = "pslider-popup__msg pslider-popup__msg--err";
            })
            .finally(function(){
                btn.classList.remove("pslider-popup__btn--loading");
                btn.disabled = false;
            });
        });
    }
})();',
        $popup_id,
        $instance
    );
    wp_add_inline_script( 'swiper', $popup_js );
endif;
