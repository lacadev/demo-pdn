<?php
namespace App\Features\SmartSearch;

/**
 * SmartSearch REST API Endpoint
 *
 * Exposes GET /wp-json/lacadev/v1/search-index
 * Returns a lightweight JSON array used by the client-side Fuse.js index.
 *
 * Response is cached server-side as a transient (30 min) and
 * sent with Cache-Control: public for CDN caching on low-traffic sites.
 *
 * @package App\Features\SmartSearch
 */
class SmartSearchEndpoint
{
    private const TRANSIENT_KEY = 'lacadev_search_index';
    private const TRANSIENT_TTL = 1800; // 30 minutes
    private const MAX_POSTS     = 500;  // safety cap

    public function __construct()
    {
        add_action('rest_api_init',             [$this, 'register']);
        add_action('save_post',                 [$this, 'invalidate']);
        add_action('delete_post',               [$this, 'invalidate']);
        add_action('trashed_post',              [$this, 'invalidate']);
        add_action('wp_ajax_laca_bust_search_index', [$this, 'bustIndexAjax']);
    }

    /** Register the REST route */
    public function register(): void
    {
        register_rest_route('lacadev/v1', '/search-index', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => [
                'types' => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    /** REST handler */
    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $cached = get_transient(self::TRANSIENT_KEY);

        if ($cached !== false) {
            return $this->response($cached);
        }

        $index = $this->buildIndex();
        set_transient(self::TRANSIENT_KEY, $index, self::TRANSIENT_TTL);

        return $this->response($index);
    }

    /** Build the search index payload */
    private function buildIndex(): array
    {
        $post_types = apply_filters(
            'lacadev/search/post_types',
            array_keys(get_post_types(['public' => true, 'exclude_from_search' => false]))
        );

        $posts = get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => self::MAX_POSTS,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'all',
        ]);

        $index = [];

        foreach ($posts as $post) {
            $thumb_url = '';
            if (has_post_thumbnail($post->ID)) {
                $thumb_url = get_the_post_thumbnail_url($post->ID, 'mobile') ?: '';
            }

            $excerpt = get_the_excerpt($post);
            if (empty($excerpt)) {
                $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 20, '…');
            }

            $index[] = [
                'id'      => $post->ID,
                'title'   => get_the_title($post->ID),
                'excerpt' => $excerpt,
                'url'     => get_permalink($post->ID),
                'type'    => $post->post_type,
                'thumb'   => $thumb_url,
                'date'    => get_the_date('Y-m-d', $post->ID),
            ];
        }

        return $index;
    }

    /** Build a cacheable WP_REST_Response */
    private function response(array $data): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data, 200);
        $response->header('Cache-Control', 'public, max-age=1800, stale-while-revalidate=3600');
        $response->header('X-Index-Count', (string) count($data));
        return $response;
    }

    /** Bust the transient cache when any post changes */
    public function invalidate(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post || $post->post_status === 'auto-draft') {
            return;
        }
        delete_transient(self::TRANSIENT_KEY);
    }

    public function bustIndexAjax(): void
    {
        check_ajax_referer('laca_bust_search_index', 'nonce');

        if (!current_user_can('edit_theme_options') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        delete_transient(self::TRANSIENT_KEY);
        wp_send_json_success(['message' => 'Search index cache cleared']);
    }
}
