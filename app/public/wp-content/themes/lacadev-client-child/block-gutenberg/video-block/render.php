<?php
/**
 * Video Block - render.php
 *
 * @package LacaDev
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Appearance attributes ──────────────────────────────────────────────────
$bg_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' ) ? $attributes['bgColor'] : '#0f0f0f';
$bg_opacity   = max( 0, min( 100, intval( $attributes['bgOpacity'] ?? 100 ) ) );
$r = hexdec( substr( $bg_color, 1, 2 ) );
$g = hexdec( substr( $bg_color, 3, 2 ) );
$b = hexdec( substr( $bg_color, 5, 2 ) );
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ( $bg_opacity / 100 ) . ')';


$source_type     = isset( $attributes['sourceType'] ) ? $attributes['sourceType'] : 'url';
$video_url       = isset( $attributes['videoUrl'] ) ? esc_url( $attributes['videoUrl'] ) : '';
$video_file      = isset( $attributes['videoFileUrl'] ) ? esc_url( $attributes['videoFileUrl'] ) : '';
$poster_url      = isset( $attributes['posterUrl'] ) ? esc_url( $attributes['posterUrl'] ) : '';
$autoplay        = ! empty( $attributes['autoplay'] );
$loop            = ! empty( $attributes['loop'] );
$muted           = ! empty( $attributes['muted'] );
$controls        = isset( $attributes['controls'] ) ? (bool) $attributes['controls'] : true;

// Overlay
$overlay_enabled = ! empty( $attributes['overlayEnabled'] );
$overlay_color   = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
$overlay_opacity = isset( $attributes['overlayOpacity'] ) ? (int) $attributes['overlayOpacity'] : 40;
$overlay_text      = isset( $attributes['overlayText'] ) ? $attributes['overlayText'] : '';
$overlay_font_size = isset( $attributes['overlayFontSize'] ) ? (int) $attributes['overlayFontSize'] : 16;
$overlay_text_color = isset( $attributes['overlayTextColor'] ) ? $attributes['overlayTextColor'] : '#ffffff';
$overlay_text_align = isset( $attributes['overlayTextAlign'] ) ? $attributes['overlayTextAlign'] : 'center';
$overlay_vertical_align = isset( $attributes['overlayVerticalAlign'] ) ? $attributes['overlayVerticalAlign'] : 'center';
$text_align = $overlay_text_align === 'flex-start' ? 'left' : ( $overlay_text_align === 'flex-end' ? 'right' : 'center' );

// Tính opacity 0–1 từ 0–100
$opacity_css = round( $overlay_opacity / 100, 2 );

// Không render nếu không có video
$has_video = ( 'url' === $source_type && $video_url ) || ( 'file' === $source_type && $video_file );
if ( ! $has_video ) {
	return;
}

// Wrapper classes
$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'laca-video-block' ] );

/**
 * Helper: parse iframe từ các URL phổ biến
 */
if ( ! function_exists( 'lacadev_parse_video_url' ) ) {
	function lacadev_parse_video_url( string $url ): array {
		$result = [ 'type' => 'unknown', 'embed' => '' ];

		// YouTube
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
			$result['type']  = 'youtube';
			$result['embed'] = 'https://www.youtube.com/embed/' . $m[1];
			return $result;
		}

		// Vimeo
		if ( preg_match( '/vimeo\.com\/(\d+)/', $url, $m ) ) {
			$result['type']  = 'vimeo';
			$result['embed'] = 'https://player.vimeo.com/video/' . $m[1];
			return $result;
		}

		$result['type'] = 'direct';
		return $result;
	}
}

?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="laca-video-block__inner" style="background:<?php echo esc_attr($bg_rgba); ?>;">
        <div class="laca-video-block__media-wrap">
        <?php if ( 'url' === $source_type && $video_url ) :
            $parsed = lacadev_parse_video_url( $video_url );

            if ( in_array( $parsed['type'], [ 'youtube', 'vimeo' ], true ) ) :
                $embed_url = $parsed['embed'];

                // Thêm params autoplay / loop
                $params = [];
                if ( $autoplay ) $params[] = ( 'youtube' === $parsed['type'] ) ? 'autoplay=1' : 'autoplay=1';
                if ( $loop )     $params[] = ( 'youtube' === $parsed['type'] ) ? 'loop=1' : 'loop=1';
                if ( $muted )    $params[] = ( 'youtube' === $parsed['type'] ) ? 'mute=1' : 'muted=1';
                if ( $params )   $embed_url .= '?' . implode( '&', $params );
            ?>
                <div class="laca-video-block__iframe-wrap">
                    <iframe
                        src="<?php echo esc_url( $embed_url ); ?>"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        loading="lazy"
                        title="<?php esc_attr_e( 'Video nhúng', 'lacadev' ); ?>"
                    ></iframe>
                </div>
            <?php else : // direct URL — dùng thẻ <video> ?>
                <div class="laca-video-block__video-wrap">
                    <video
                        src="<?php echo esc_url( $video_url ); ?>"
                        <?php if ( $poster_url ) : ?>poster="<?php echo esc_url( $poster_url ); ?>"<?php endif; ?>
                        <?php if ( $controls ) echo 'controls'; ?>
                        <?php if ( $autoplay ) echo 'autoplay'; ?>
                        <?php if ( $loop )     echo 'loop'; ?>
                        <?php if ( $muted )    echo 'muted'; ?>
                        playsinline
                        preload="metadata"
                    ></video>
                </div>
            <?php endif; ?>

        <?php elseif ( 'file' === $source_type && $video_file ) : ?>
            <div class="laca-video-block__video-wrap">
                <video
                    src="<?php echo esc_url( $video_file ); ?>"
                    <?php if ( $poster_url ) : ?>poster="<?php echo esc_url( $poster_url ); ?>"<?php endif; ?>
                    <?php if ( $controls ) echo 'controls'; ?>
                    <?php if ( $autoplay ) echo 'autoplay'; ?>
                    <?php if ( $loop )     echo 'loop'; ?>
                    <?php if ( $muted )    echo 'muted'; ?>
                    playsinline
                    preload="metadata"
                ></video>
            </div>
        <?php endif; ?>

        <?php if ( $overlay_enabled ) : ?>
            <div
                class="laca-video-block__overlay"
                style="background-color:<?php echo esc_attr( $overlay_color ); ?>;opacity:<?php echo esc_attr( $opacity_css ); ?>;"
                aria-hidden="true"
            ></div>
            <?php if ( $content || $overlay_text ) : ?>
            <div class="laca-video-block__overlay-text" style="align-items:<?php echo esc_attr( $overlay_vertical_align ); ?>;justify-content:<?php echo esc_attr( $overlay_text_align ); ?>;color:<?php echo esc_attr( $overlay_text_color ); ?>;font-size:<?php echo esc_attr( $overlay_font_size ); ?>px;text-align:<?php echo esc_attr( $text_align ); ?>;width:100%;flex-direction:column;">
                <?php echo $content ? $content : wp_kses_post( $overlay_text ); ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        </div><!-- .laca-video-block__media-wrap -->
    </div><!-- .laca-video-block__inner -->
</section>

