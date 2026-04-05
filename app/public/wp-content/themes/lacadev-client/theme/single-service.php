<?php
	/**
	 * App Layout: layouts/app.php
	 *
	 * This is the template that is used for displaying all posts by default.
	 *
	 * @link    https://codex.wordpress.org/Template_Hierarchy
	 *
	 * @package WPEmergeTheme
	 */

	theBreadcrumb();
?>

<article class="single-template">
	<!-- Hero Section -->
	<?php get_template_part('template-parts/post-hero'); ?>

	<div class="container">
		<!-- Featured Image -->
		<?php if (has_post_thumbnail()) : ?>
			<div class="post-featured-image">
				<?php echo getResponsivePostThumbnail(get_the_ID(), 'full'); ?>
			</div>
		<?php endif; ?>

		<div class="post-content-wrapper">
			<div class="toc">
				<?php echo do_shortcode('[ez-toc]') ?>
			</div>
			<!-- Content Body -->
			<div class="post-body">
				<?php theContent(); ?>
			</div>

			<div class="post-share">
				<?php get_template_part('template-parts/share_box'); ?>
			</div>

			<?php get_template_part('template-parts/rating-box'); ?>

			<div class="post-comments">
				<?php
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;
				?>
			</div>
		</div>
	</div>
</article>
			