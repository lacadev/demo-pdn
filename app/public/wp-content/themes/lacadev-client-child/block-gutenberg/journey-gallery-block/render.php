<?php
/**
 * Journey Gallery Block — render.php
 *
 * @package LacaDev
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading          = esc_html( $attributes['heading'] ?? '' );
$description      = wp_kses_post( $attributes['description'] ?? '' );
$items            = $attributes['items'] ?? [];
$columns          = (int) ( $attributes['columns'] ?? 2 );
$heading_tag      = in_array( $attributes['headingTag'] ?? 'h2', [ 'h2', 'h3', 'h4' ], true )
                    ? $attributes['headingTag']
                    : 'h2';
$container_layout = in_array( $attributes['containerLayout'] ?? 'container', [ 'container', 'container-fluid' ], true )
                    ? $attributes['containerLayout']
                    : 'container';

// Clamp columns 1-4
$columns = max( 1, min( 4, $columns ) );

$wrapper_attrs = get_block_wrapper_attributes( [
    'class' => 'block-journey-gallery',
] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="<?php echo esc_attr( $container_layout ); ?>">

        <?php if ( $heading || $description ) : ?>
        <div class="block-journey-gallery__header">
            <?php if ( $heading ) : ?>
            <<?php echo $heading_tag; ?> class="block-journey-gallery__heading">
                <?php echo $heading; ?>
            </<?php echo $heading_tag; ?>>
            <?php endif; ?>

            <?php if ( $description ) : ?>
            <p class="block-journey-gallery__description">
                <?php echo $description; ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $items ) ) : ?>
        <div
            class="block-journey-gallery__grid"
            style="--jg-cols: <?php echo $columns; ?>;"
        >
            <?php foreach ( $items as $item ) :
                $image_url = esc_url( $item['imageUrl'] ?? '' );
                $image_alt = esc_attr( $item['imageAlt'] ?? '' );

                if ( ! $image_url ) {
                    continue;
                }
            ?>
            <div class="block-journey-gallery__item">
                <img
                    src="<?php echo $image_url; ?>"
                    alt="<?php echo $image_alt; ?>"
                    class="block-journey-gallery__img"
                    loading="lazy"
                    decoding="async"
                />
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>
