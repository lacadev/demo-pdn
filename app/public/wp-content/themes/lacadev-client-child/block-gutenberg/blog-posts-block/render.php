<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Blog Posts Block — render.php
 * Layout: Lưới 3 hoặc 4 cột — editorial journal style.
 * Hỗ trợ: chọn post type, taxonomy/term, auto/manual, số lượng, sắp xếp.
 *
 * @package lacadev-client
 */

$attr            = $attributes;
$container_class = $attr['containerLayout'] ?? 'container';
$bg_color        = $attr['backgroundColor'] ?? '';
$inline_bg       = $bg_color ? ' style="background-color: ' . esc_attr( $bg_color ) . ';"' : '';

// Section heading
$section_badge = esc_html( $attr['sectionBadge'] ?? 'Editorial Journal' );
$section_title = esc_html( $attr['sectionTitle'] ?? 'Furniture Blog' );
$cta_text      = esc_html( $attr['ctaText']      ?? 'Read Story' );

// Layout
$columns = intval( $attr['columns'] ?? 3 );
$cols_class = $columns === 4
    ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12'
    : 'grid grid-cols-1 md:grid-cols-3 gap-12';

// Query params
$post_type      = sanitize_key( $attr['postType']   ?? 'post' );
$taxonomy       = sanitize_key( $attr['taxonomy']   ?? '' );
$selected_terms = array_map( 'intval', $attr['selectedTerms'] ?? [] );
$posts_count    = intval( $attr['postsCount'] ?? 3 );
$order_by       = sanitize_key( $attr['orderBy']    ?? 'date' );
$order          = in_array( strtoupper( $attr['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true )
    ? strtoupper( $attr['order'] )
    : 'DESC';
$mode           = $attr['mode'] ?? 'auto';
$selected_posts = array_map( 'intval', $attr['selectedPosts'] ?? [] );

// ── Build WP_Query ───────────────────────────────────────────────────────

if ( $mode === 'manual' && ! empty( $selected_posts ) ) {
    // Chế độ thủ công: lấy đúng IDs đã chọn, giữ thứ tự
    $query_args = [
        'post_type'           => $post_type,
        'post__in'            => $selected_posts,
        'orderby'             => 'post__in',
        'posts_per_page'      => count( $selected_posts ),
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
    ];
} else {
    // Chế độ tự động
    $query_args = [
        'post_type'           => $post_type,
        'posts_per_page'      => $posts_count,
        'post_status'         => 'publish',
        'orderby'             => $order_by,
        'order'               => $order,
        'ignore_sticky_posts' => true,
    ];
    // Lọc theo taxonomy / term
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
?>

<section <?php echo get_block_wrapper_attributes( [ 'class' => 'vp-block' ] ); ?><?php echo $inline_bg; ?>>
    <div class="<?php echo esc_attr( $container_class ); ?>">
        <div class="px-6 md:px-12 py-32">

            <?php if ( $section_badge || $section_title ) : ?>
                <div class="text-center mb-20">
                    <?php if ( $section_badge ) : ?>
                        <span class="text-secondary font-label tracking-[0.3em] uppercase text-xs mb-4 block">
                            <?php echo $section_badge; ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $section_title ) : ?>
                        <h2 class="text-4xl md:text-5xl font-headline">
                            <?php echo $section_title; ?>
                        </h2>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ( $query->have_posts() ) : ?>
                <div class="<?php echo esc_attr( $cols_class ); ?>">
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $post_id     = get_the_ID();
                        $post_link   = esc_url( get_permalink() );
                        $post_title  = get_the_title();
                        $post_exc    = wp_trim_words( get_the_excerpt(), 22, '...' );
                        $thumb_url   = get_the_post_thumbnail_url( $post_id, 'large' );
                        $thumb_alt   = esc_attr( get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true ) ?: $post_title );

                        // Lấy term đầu tiên của taxonomy đang dùng
                        $cat_name = '';
                        if ( $taxonomy ) {
                            $terms_list = get_the_terms( $post_id, $taxonomy );
                            if ( $terms_list && ! is_wp_error( $terms_list ) ) {
                                $cat_name = esc_html( $terms_list[0]->name );
                            }
                        } else {
                            // Fallback: category cho post type mặc định
                            $cats = get_the_category( $post_id );
                            if ( $cats ) {
                                $cat_name = esc_html( $cats[0]->name );
                            }
                        }
                        ?>
                        <article class="group">
                            <a href="<?php echo $post_link; ?>" class="block overflow-hidden rounded-lg mb-8 aspect-video bg-surface-container-high">
                                <?php if ( $thumb_url ) : ?>
                                    <img
                                        src="<?php echo esc_url( $thumb_url ); ?>"
                                        alt="<?php echo $thumb_alt; ?>"
                                        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                <?php else : ?>
                                    <div class="w-full h-full flex items-center justify-center text-on-surface-variant text-sm">
                                        <?php esc_html_e( 'Chưa có ảnh', 'laca' ); ?>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <?php if ( $cat_name ) : ?>
                                <span class="text-xs font-label uppercase text-on-surface-variant tracking-widest block mb-3">
                                    <?php echo $cat_name; ?>
                                </span>
                            <?php endif; ?>

                            <h3 class="text-2xl font-headline mb-4 group-hover:text-primary transition-colors">
                                <a href="<?php echo $post_link; ?>"><?php echo esc_html( $post_title ); ?></a>
                            </h3>

                            <?php if ( $post_exc ) : ?>
                                <p class="text-on-surface-variant text-sm mb-6 leading-relaxed">
                                    <?php echo esc_html( $post_exc ); ?>
                                </p>
                            <?php endif; ?>

                            <a
                                href="<?php echo $post_link; ?>"
                                class="blog-posts-cta inline-flex items-center text-on-surface font-label gap-1 group-hover:gap-3 transition-all"
                                aria-label="<?php echo esc_attr( sprintf( __( '%s — %s', 'laca' ), $cta_text, $post_title ) ); ?>"
                            >
                                <?php echo $cta_text; ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                            </a>
                        </article>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
            <?php else : ?>
                <p class="text-center text-on-surface-variant py-16">
                    <?php esc_html_e( 'Chưa có bài viết nào.', 'laca' ); ?>
                </p>
            <?php endif; ?>

        </div>
    </div>
</section>
