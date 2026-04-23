<?php

/**
 * Project Archive AJAX Handler
 *
 * Handle AJAX filter by taxonomy project_cat + pagination.
 * Action: lacadev_project_archive_load.
 *
 * @package LacaDevClientChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProjectAjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_lacadev_project_archive_load', [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_lacadev_project_archive_load', [ $this, 'handle' ] );
	}

	public function handle(): void {
		if ( ! check_ajax_referer( 'theme_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
		}

		$cat_slug       = isset( $_POST['cat_slug'] ) ? sanitize_title( wp_unslash( $_POST['cat_slug'] ) ) : '';
		$paged          = max( 1, (int) ( $_POST['paged'] ?? 1 ) );
		$posts_per_page = max( 1, min( 48, (int) ( $_POST['posts_per_page'] ?? 12 ) ) );

		$args = [
			'post_type'      => 'project',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'no_found_rows'  => false,
		];

		if ( $cat_slug ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'taxonomy' => 'project_cat',
					'field'    => 'slug',
					'terms'    => $cat_slug,
				],
			];
		}

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_card( get_the_ID() );
			}
		} else {
			echo '<div class="laca-gallery-list__empty"><p>' . esc_html__( 'Chưa có dự án nào.', 'laca' ) . '</p></div>';
		}
		$cards_html = ob_get_clean();

		ob_start();
		echo paginate_links( [
			'base'      => '%_%',
			'format'    => '?paged=%#%',
			'current'   => $paged,
			'total'     => $query->max_num_pages,
			'prev_text' => '&lsaquo;',
			'next_text' => '&rsaquo;',
			'type'      => 'plain',
		] ); // phpcs:ignore WordPress.Security.EscapeOutput
		$pagination_html = ob_get_clean();

		$active_label = __( 'Tất cả', 'laca' );
		if ( $cat_slug ) {
			$term = get_term_by( 'slug', $cat_slug, 'project_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$active_label = $term->name;
			}
		}

		wp_reset_postdata();

		wp_send_json_success( [
			'html'         => $cards_html,
			'pagination'   => $pagination_html,
			'max_pages'    => (int) $query->max_num_pages,
			'active_label' => $active_label,
		] );
	}

	private function render_card( int $post_id ): void {
		$investor   = get_post_meta( $post_id, '_investor', true );
		$location   = get_post_meta( $post_id, '_location', true );
		$floors     = get_post_meta( $post_id, '_floors', true );
		$front_area = get_post_meta( $post_id, '_front_area', true );
		?>
		<article class="laca-gallery-card">
			<a class="laca-gallery-card__link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
				<div class="laca-gallery-card__img">
					<?php if ( has_post_thumbnail( $post_id ) ) : ?>
						<?php echo get_the_post_thumbnail( $post_id, 'large', [ 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ] ); ?>
					<?php else : ?>
						<div class="laca-gallery-card__img-placeholder"></div>
					<?php endif; ?>
				</div>

				<div class="laca-gallery-card__body">
					<h3 class="laca-gallery-card__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
					<ul class="laca-gallery-card__meta">
						<?php if ( $investor ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Chủ đầu tư:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $investor ); ?></span></li>
						<?php endif; ?>
						<?php if ( $location ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Địa điểm:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $location ); ?></span></li>
						<?php endif; ?>
						<?php if ( $floors ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Số tầng:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $floors ); ?></span></li>
						<?php endif; ?>
						<?php if ( $front_area ) : ?>
							<li><span class="laca-gallery-card__meta-label"><?php esc_html_e( 'Mặt tiền:', 'laca' ); ?></span><span class="laca-gallery-card__meta-value"><?php echo esc_html( $front_area ); ?></span></li>
						<?php endif; ?>
					</ul>
				</div>
			</a>
		</article>
		<?php
	}
}

new ProjectAjaxHandler();

