<?php
namespace App\Features;

/**
 * RecommendationEngine
 *
 * Replaces the basic tag/category fallback logic in RelatedPosts with a
 * weighted taxonomy-scoring algorithm:
 *
 *   score(candidate) =
 *       Σ( shared_terms_in_taxonomy × taxonomy_weight )
 *     + recency_bonus  (0–1 based on post age)
 *     + popularity_bonus  (0–1 based on comment count, optional)
 *
 * Results are cached per post as a transient (1 hour).
 *
 * Integrates by hooking `lacadev/recommendations/posts` filter, which
 * RelatedPosts (and other consumers) can use instead of WP_Query directly.
 *
 * Shortcode: [laca_recommendations count="4" show_score="0"]
 *
 * @package App\Features
 */
class RecommendationEngine
{
    /** Transient TTL in seconds */
    private const CACHE_TTL = 3600;

    /** Default taxonomy weights */
    private const DEFAULT_WEIGHTS = [
        'post_tag'  => 3.0,
        'category'  => 2.0,
        // Custom taxonomies default to 1.0 (see getWeight)
    ];

    public function init(): void
    {
        // Replace RelatedPosts query with scored results
        add_filter('lacadev/related_posts/query', [$this, 'scoredQuery'], 10, 3);

        // Shortcode
        add_shortcode('laca_recommendations', [$this, 'renderShortcode']);

        // Bust cache when a post is updated
        add_action('save_post',   [$this, 'bustCache']);
        add_action('delete_post', [$this, 'bustCache']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return recommended posts for $postId, sorted by descending score.
     *
     * @return \WP_Post[]
     */
    public function recommend(int $postId, int $count = 3): array
    {
        $cacheKey = "lacadev_rec_{$postId}_{$count}";
        $cached   = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $scored = $this->buildScores($postId, max(50, $count * 10));
        arsort($scored); // highest score first
        $topIds = array_slice(array_keys($scored), 0, $count);

        if (empty($topIds)) {
            return [];
        }

        $posts = get_posts([
            'post__in'    => $topIds,
            'orderby'     => 'post__in',
            'post_type'   => get_post_type($postId),
            'post_status' => 'publish',
            'numberposts' => $count,
        ]);

        set_transient($cacheKey, $posts, self::CACHE_TTL);
        return $posts;
    }

    /** Filter hook — replaces the query inside RelatedPosts::getRelated() */
    public function scoredQuery(array $posts, int $postId, int $count): array
    {
        $recommended = $this->recommend($postId, $count);
        return !empty($recommended) ? $recommended : $posts;
    }

    public function bustCache(int $postId): void
    {
        // Bust cache for this post and posts that likely featured it
        $post_type = get_post_type($postId);
        if (!$post_type) return;

        delete_transient("lacadev_rec_{$postId}_3");
        delete_transient("lacadev_rec_{$postId}_4");
        delete_transient("lacadev_rec_{$postId}_6");

        // Also bust sibling posts' caches (they may have recommended this one)
        // For performance, we only bust a limited set
        $siblings = get_posts([
            'post_type'   => $post_type,
            'numberposts' => 20,
            'post_status' => 'publish',
            'post__not_in' => [$postId],
            'fields'      => 'ids',
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);
        foreach ($siblings as $id) {
            delete_transient("lacadev_rec_{$id}_3");
            delete_transient("lacadev_rec_{$id}_4");
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scoring
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build a [ post_id => float score ] map for candidates.
     *
     * @param int $postId    The reference post
     * @param int $pool      How many candidates to score (larger = more accurate)
     * @return array<int,float>
     */
    private function buildScores(int $postId, int $pool = 50): array
    {
        $postType = get_post_type($postId);
        if (!$postType) {
            return [];
        }

        // Get all public taxonomies for this post type
        $taxonomies = get_object_taxonomies($postType, 'names');
        $taxonomies = array_filter($taxonomies, fn($t) => !in_array($t, ['post_format'], true));

        if (empty($taxonomies)) {
            return [];
        }

        // Build term map for reference post: taxonomy => term_ids[]
        $refTerms = [];
        foreach ($taxonomies as $taxonomy) {
            $ids = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($ids) && !empty($ids)) {
                $refTerms[$taxonomy] = $ids;
            }
        }

        if (empty($refTerms)) {
            return []; // No terms — can't score
        }

        // Fetch candidate posts that share at least one term with the reference
        $allTermIds = array_unique(array_merge(...array_values($refTerms)));
        $candidates = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'post__not_in'   => [$postId],
            'numberposts'    => $pool,
            'no_found_rows'  => true,
            'tax_query'      => [[
                'taxonomy' => count($refTerms) === 1 ? array_key_first($refTerms) : 'post_tag',
                'field'    => 'term_id',
                'terms'    => $allTermIds,
                'operator' => 'IN',
            ]],
        ]);

        if (empty($candidates)) {
            // Fall back to recent posts if no taxonomy overlap found
            $candidates = get_posts([
                'post_type'    => $postType,
                'post_status'  => 'publish',
                'post__not_in' => [$postId],
                'numberposts'  => $pool,
                'no_found_rows'=> true,
                'orderby'      => 'date',
                'order'        => 'DESC',
            ]);
        }

        if (empty($candidates)) {
            return [];
        }

        // ── Score each candidate ──────────────────────────────────────────
        $now    = time();
        $oldest = strtotime($candidates[count($candidates) - 1]->post_date ?? 'now');
        $newest = strtotime($candidates[0]->post_date ?? 'now');
        $span   = max(1, $newest - $oldest);

        $scores = [];

        foreach ($candidates as $candidate) {
            $score = 0.0;

            // Taxonomy overlap score
            foreach ($refTerms as $taxonomy => $termIds) {
                $candidateTerms = wp_get_object_terms($candidate->ID, $taxonomy, ['fields' => 'ids']);
                if (is_wp_error($candidateTerms)) {
                    continue;
                }
                $shared = count(array_intersect($termIds, $candidateTerms));
                if ($shared > 0) {
                    $weight = $this->getWeight($taxonomy);
                    $score += $shared * $weight;
                }
            }

            // Recency bonus: 0–0.5 (newer posts get a small boost)
            $age   = strtotime($candidate->post_date ?? 'now');
            $score += 0.5 * (($age - $oldest) / $span);

            // Popularity bonus: 0–0.3 (comment count)
            if ($candidate->comment_count > 0) {
                $score += min(0.3, $candidate->comment_count / 100);
            }

            $scores[$candidate->ID] = $score;
        }

        return $scores;
    }

    private function getWeight(string $taxonomy): float
    {
        $weights = apply_filters('lacadev/recommendations/weights', self::DEFAULT_WEIGHTS);
        return (float) ($weights[$taxonomy] ?? 1.0);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Shortcode
    // ─────────────────────────────────────────────────────────────────────

    public function renderShortcode(array $atts): string
    {
        $atts  = shortcode_atts(['count' => 3, 'show_score' => 0], $atts, 'laca_recommendations');
        $count = absint($atts['count']) ?: 3;
        $id    = get_the_ID();

        if (!$id) {
            return '';
        }

        $posts = $this->recommend($id, $count);
        if (empty($posts)) {
            return '';
        }

        ob_start();
        echo '<div class="laca-related-posts laca-recommendations">';
        echo '<h3 class="laca-related-posts__title">Có thể bạn quan tâm</h3>';
        echo '<div class="laca-related-posts__grid">';
        foreach ($posts as $post) {
            setup_postdata($post);
            ?>
            <article class="laca-related-posts__item">
                <a href="<?php echo esc_url(get_permalink($post)); ?>" class="laca-related-posts__thumb-link">
                    <?php if (has_post_thumbnail($post)): ?>
                        <?php echo get_the_post_thumbnail($post, 'medium', ['class' => 'laca-related-posts__thumb', 'loading' => 'lazy']); ?>
                    <?php else: ?>
                        <div class="laca-related-posts__no-thumb"></div>
                    <?php endif; ?>
                </a>
                <div class="laca-related-posts__meta">
                    <span class="laca-related-posts__date"><?php echo esc_html(get_the_date('d/m/Y', $post)); ?></span>
                </div>
                <h4 class="laca-related-posts__post-title">
                    <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                </h4>
                <p class="laca-related-posts__excerpt">
                    <?php echo esc_html(wp_trim_words(get_the_excerpt($post), 15, '…')); ?>
                </p>
            </article>
            <?php
        }
        wp_reset_postdata();
        echo '</div></div>';
        return ob_get_clean();
    }
}
