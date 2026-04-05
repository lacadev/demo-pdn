<?php
/**
 * App Layout: layouts/app.php
 *
 * This is the template that is used for displaying all posts by default.
 *
 * @package WPEmergeTheme
 */

theBreadcrumb();

// Get the current layout preference for taxonomy archives
$layout = 'card';
if (is_tax() || is_category() || is_tag()) {
    $term_id = get_queried_object_id();
    $layout = carbon_get_term_meta($term_id, 'crb_archive_layout') ?: 'card';
}

// Map 'block-blog' style classes if using card layout
$wrapper_class = ($layout === 'staggered') ? 'block-staggered-blog' : 'block-blog';
?>

<main class="archive-post <?php echo esc_attr($wrapper_class); ?>">
    <?php get_template_part('template-parts/page-hero'); ?>

    <div class="container">
        <div class="archive-content">
            <?php if (have_posts()) : ?>
                <?php
                // START: N+1 Prevention
                $current_post_type = get_post_type() ?: 'post';
                update_post_caches($GLOBALS['wp_query']->posts, $current_post_type, true, true);
                update_object_term_cache(wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'), $current_post_type);
                // END: N+1 Prevention
                ?>

                <div class="<?php echo ($layout === 'staggered') ? 'staggered-list' : 'blog-list'; ?>">
                    <?php 
                    $index = 0;
                    while (have_posts()) : the_post(); 
                        if ($layout === 'staggered') :
                            $is_even = ($index % 2 !== 0);
                            $excerpt = get_the_excerpt();
                            $excerpt = wp_trim_words($excerpt, 50, '...');
                            ?>
                            <div class="staggered-item <?php echo $is_even ? 'staggered-item--even' : 'staggered-item--odd'; ?>">
                                <div class="staggered-item__content">
                                    <h3 class="staggered-item__title">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    <div class="staggered-item__desc">
                                        <?php echo esc_html($excerpt); ?>
                                    </div>
                                </div>
                                <div class="staggered-item__image">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php theResponsivePostThumbnail('tablet', ['alt' => esc_attr(get_the_title())]); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        else :
                            $author_name = get_the_author();
                            $time_diff = human_time_diff(get_the_time('U'), current_time('timestamp'));
                            $time_diff_text = sprintf(__('%s ago', 'laca'), $time_diff);
                            ?>
                            <div class="blog-item">
                                <div class="blog-card">
                                    <a href="<?php the_permalink(); ?>" class="card-link">
                                        <div class="card-image-wrap">
                                            <?php theResponsivePostThumbnail('mobile', ['alt' => esc_attr(get_the_title())]); ?>
                                        </div>
                                        <div class="card-body">
                                            <h3 class="card-title"><?php the_title(); ?></h3>
                                            <div class="card-meta">
                                                <span class="meta-author"><?php printf(__('By %s', 'laca'), esc_html($author_name)); ?></span>
                                                <span class="meta-date"><?php echo esc_html($time_diff_text); ?></span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <?php
                        endif;
                        $index++;
                    endwhile; 
                    ?>
                </div>

                <?php thePagination(); ?>

            <?php else : ?>
                <div class="no-posts">
                    <p><?php _e('Chưa có bài viết nào trong mục này.', 'laca'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php wp_reset_postdata(); ?>