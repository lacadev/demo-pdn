<?php
/**
 * posts-highlight-block — render.php
 * Layout: 1 bài lớn (cột trái, span 2 rows) + 4 bài nhỏ (cột phải, 2×2)
 * Attribute keys đồng bộ với edit.js: selectedTerms, postsCount, selectedPosts
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Attributes ─────────────────────────────────────────────────────────────────
$section_title   = esc_html( $attributes['sectionTitle']   ?? 'Tin Mới Nhất' );
$cta_text        = esc_html( $attributes['ctaText']        ?? 'Xem Chi Tiết' );
$post_type       = sanitize_key( $attributes['postType']   ?? 'post' );
$taxonomy        = sanitize_key( $attributes['taxonomy']   ?? '' );
$selected_terms  = array_map( 'absint', (array) ( $attributes['selectedTerms'] ?? [] ) );
$mode            = in_array( $attributes['mode'] ?? 'auto', [ 'auto', 'manual' ], true )
                    ? $attributes['mode'] : 'auto';
$orderby         = sanitize_key( $attributes['orderBy']    ?? 'date' );
$order           = strtoupper( $attributes['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
$posts_count     = max( 3, min( 20, intval( $attributes['postsCount'] ?? 5 ) ) );
$selected_posts  = array_map( 'absint', (array) ( $attributes['selectedPosts'] ?? [] ) );

$safe_orderby = in_array( $orderby, [ 'date', 'title', 'menu_order', 'comment_count', 'modified' ], true )
                    ? $orderby : 'date';

// ── WP_Query ───────────────────────────────────────────────────────────────────
if ( $mode === 'manual' && ! empty( $selected_posts ) ) {
    $query_args = [
        'post_type'           => $post_type,
        'post__in'            => $selected_posts,
        'orderby'             => 'post__in',
        'posts_per_page'      => count( $selected_posts ),
        'post_status'         => 'publish',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];
} else {
    $query_args = [
        'post_type'           => $post_type,
        'posts_per_page'      => $posts_count,
        'post_status'         => 'publish',
        'orderby'             => $safe_orderby,
        'order'               => $order,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];

    if ( $taxonomy && ! empty( $selected_terms ) ) {
        $query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $selected_terms,
            ],
        ];
    }
}

$loop  = new WP_Query( $query_args );
$posts = $loop->posts;
wp_reset_postdata();

if ( empty( $posts ) ) return;

// Tách: 5 bài đầu vào grid chính, từ bài 6 trở đi vào extra grid 3 cột
$main_posts  = array_slice( $posts, 0, 5 );
$extra_posts = array_slice( $posts, 5 );

// ── Helpers (closures — tránh redeclare khi block xuất hiện nhiều lần) ─────────
$get_thumb = static function ( int $post_id, bool $featured ): string {
    $size = $featured ? 'large' : 'medium_large';
    if ( ! has_post_thumbnail( $post_id ) ) return '';
    return esc_url( get_the_post_thumbnail_url( $post_id, $size ) );
};

$get_cat = static function ( WP_Post $post, string $taxonomy ): string {
    if ( $taxonomy ) {
        $terms = get_the_terms( $post, $taxonomy );
        if ( $terms && ! is_wp_error( $terms ) ) {
            return esc_html( $terms[0]->name );
        }
    }
    $cats = get_the_category( $post->ID );
    return $cats ? esc_html( $cats[0]->name ) : '';
};

// ── Output ─────────────────────────────────────────────────────────────────────
$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'block-posts-highlight' ] );
?>

<section <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <div class="container">

        <?php if ( $section_title ) : ?>
        <div class="phb__header">
            <h2 class="phb__heading"><?php echo $section_title; // phpcs:ignore ?></h2>
        </div>
        <?php endif; ?>

        <div class="phb__grid">

            <?php foreach ( $main_posts as $index => $post ) :
                $is_featured = ( $index === 0 );
                $thumb_url   = $get_thumb( $post->ID, $is_featured );
                $post_url    = esc_url( get_permalink( $post ) );
                $cat_name    = $get_cat( $post, $taxonomy );
                $title       = esc_html( get_the_title( $post ) );
                $date        = esc_html( get_the_date( 'd-m-Y', $post ) );
                $date_iso    = esc_attr( get_the_date( 'c', $post ) );

                $card_class  = 'phb__card';
                if ( $is_featured ) $card_class .= ' phb__card--featured';
            ?>

            <article
                class="<?php echo esc_attr( $card_class ); ?>"
                <?php if ( $thumb_url ) : ?>style="background-image:url('<?php echo $thumb_url; ?>')"<?php endif; ?>
            >
                <div class="phb__overlay" aria-hidden="true"></div>

                <div class="phb__body">

                    <?php if ( $cat_name ) : ?>
                    <span class="phb__cat"><?php echo $cat_name; // phpcs:ignore ?></span>
                    <?php endif; ?>

                    <<?php echo $is_featured ? 'h2' : 'h3'; ?> class="phb__title"><?php echo $title; ?></<?php echo $is_featured ? 'h2' : 'h3'; ?>>

                    <?php if ( $date ) : ?>
                    <time class="phb__date" datetime="<?php echo $date_iso; ?>"><?php echo $date; ?></time>
                    <?php endif; ?>
                </div>
            </article>

            <?php endforeach; ?>

        </div><!-- /.phb__grid -->

        <?php if ( ! empty( $extra_posts ) ) : ?>
        <div class="phb__extra-grid">
            <?php foreach ( $extra_posts as $post ) :
                $thumb_url = $get_thumb( $post->ID, false );
                $post_url  = esc_url( get_permalink( $post ) );
                $cat_name  = $get_cat( $post, $taxonomy );
                $title     = esc_html( get_the_title( $post ) );
                $date      = esc_html( get_the_date( 'd-m-Y', $post ) );
                $date_iso  = esc_attr( get_the_date( 'c', $post ) );
            ?>
            <article
                class="phb__card phb__card--extra"
                <?php if ( $thumb_url ) : ?>style="background-image:url('<?php echo $thumb_url; ?>')"<?php endif; ?>
            >
                <div class="phb__overlay" aria-hidden="true"></div>
                <div class="phb__body">
                    <?php if ( $cat_name ) : ?>
                    <span class="phb__cat"><?php echo $cat_name; // phpcs:ignore ?></span>
                    <?php endif; ?>
                    <h3 class="phb__title"><?php echo $title; ?></h3>
                    <?php if ( $date ) : ?>
                    <time class="phb__date" datetime="<?php echo $date_iso; ?>"><?php echo $date; ?></time>
                    <?php endif; ?>

                </div>
            </article>
            <?php endforeach; ?>
        </div><!-- /.phb__extra-grid -->
        <?php endif; ?>

    </div><!-- /.container -->
</section>

