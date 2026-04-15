<?php
/**
 * Gutenberg Blocks Registration
 * 
 * Register ReactJS-based Gutenberg blocks
 * 
 * @package LacaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Gutenberg blocks scripts and styles
 */
function lacadev_register_gutenberg_blocks_assets() {
    // Theme root is one level above `theme/` folder (see APP_DIR constant in `theme/functions.php`)
    if (!defined('APP_DIR')) {
        return;
    }

    // Legacy/global bundle (only used for blocks that don't have their own build folder)
    $asset_file = trailingslashit(APP_DIR) . 'dist/gutenberg/index.asset.php';
    
    if (!file_exists($asset_file)) {
        return;
    }
    
    $asset = require $asset_file;
    
    // URL root of theme (one level above `theme/`) — dùng get_template_directory_uri()
    // để luôn trỏ về parent theme, không bị lệch khi child theme active.
    $theme_root_uri = dirname(get_template_directory_uri());

    // Register block editor script
    wp_register_script(
        'lacadev-gutenberg-blocks',
        $theme_root_uri . '/dist/gutenberg/index.js',
        $asset['dependencies'],
        $asset['version'],
        false
    );
}
add_action('init', 'lacadev_register_gutenberg_blocks_assets', 5);

/**
 * Register all custom blocks
 */
function lacadev_register_custom_blocks() {
    // First, register assets
    lacadev_register_gutenberg_blocks_assets();
    
    // Get all block directories - use file path, not URL
    if (!defined('APP_DIR')) {
        return;
    }

    $blocks_dir = trailingslashit(APP_DIR) . 'block-gutenberg';
    // Luôn dùng get_template_directory_uri() để trỏ về parent theme URI,
    // tránh trỏ nhầm sang child theme khi child theme đang active.
    $theme_root_uri = dirname(get_template_directory_uri());
    
    if (!is_dir($blocks_dir)) {
        return;
    }
    
    $blocks = scandir($blocks_dir);
    $registered_count = 0;
    
    foreach ($blocks as $block) {
        // Skip . and .. and index.js
        if ($block === '.' || $block === '..' || $block === 'index.js' || $block === 'debug.js') {
            continue;
        }
        
        $block_json = $blocks_dir . '/' . $block . '/block.json';
        
        if (file_exists($block_json)) {
            // Check if block has render.php for dynamic rendering
            $render_php = $blocks_dir . '/' . $block . '/render.php';
            
            // Check if block has a specific build folder (self-contained block)
            $has_individual_build = is_dir($blocks_dir . '/' . $block . '/build');
            
            $block_args = [];
            
            if ($has_individual_build) {
                $asset_file = $blocks_dir . '/' . $block . '/build/index.asset.php';
                $asset = [
                    'dependencies' => [],
                    'version' => null,
                ];

                if (file_exists($asset_file)) {
                    $asset = require $asset_file;
                }

                $editor_script_handle = 'lacadev-block-' . $block . '-editor';
                $editor_style_handle = 'lacadev-block-' . $block . '-editor';
                $style_handle = 'lacadev-block-' . $block;

                wp_register_script(
                    $editor_script_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/index.js',
                    $asset['dependencies'] ?? [],
                    $asset['version'] ?? null,
                    true
                );

                wp_register_style(
                    $editor_style_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/index.css',
                    [],
                    $asset['version'] ?? null
                );

                wp_register_style(
                    $style_handle,
                    $theme_root_uri . '/block-gutenberg/' . $block . '/build/style-index.css',
                    [],
                    $asset['version'] ?? null
                );

                $block_args['editor_script'] = $editor_script_handle;
                $block_args['editor_style'] = $editor_style_handle;
                $block_args['style'] = $style_handle;
            } else {
                // Backward compatibility for blocks that haven't been refactored
                $block_args['editor_script'] = 'lacadev-gutenberg-blocks';
            }
            
            // Add render callback if render.php exists
            if (file_exists($render_php)) {
                $block_args['render_callback'] = function($attributes, $content) use ($render_php) {
                    ob_start();
                    require $render_php;
                    return ob_get_clean();
                };
            }
            
            $result = register_block_type_from_metadata($block_json, $block_args);
            
            if ($result) {
                $registered_count++;
            }
        }
    }
    
}
add_action('init', 'lacadev_register_custom_blocks', 10);

/**
 * Register custom block category
 */
function lacadev_register_block_category($categories, $post) {
    return array_merge(
        [
            [
                'slug'  => 'lacadev-blocks',
                'title' => __('La Cà Blocks', 'laca'),
                'icon'  => 'admin-customizer',
            ],
        ],
        $categories
    );
}
add_filter('block_categories_all', 'lacadev_register_block_category', 10, 2);

/**
 * Đăng ký các blocks đã được sync về từ lacadev server.
 *
 * BlockSyncReceiver ghi files vào lacadev-client-child/block-gutenberg/{block_name}/
 * (child theme) để tách biệt với parent theme — update lacadev-client sẽ không xoá blocks đã sync.
 * Hàm này chạy sau priority 10 để không xảy ra conflict với parent theme register.
 */
function lacadev_child_register_synced_blocks(): void
{
    // Blocks được BlockSyncReceiver ghi vào child theme để tách biệt với parent theme.
    // get_stylesheet_directory() → .../lacadev-client-child/theme (khi child active)
    // dirname() → .../lacadev-client-child/
    $childBlocksDir = dirname(get_stylesheet_directory()) . '/block-gutenberg';

    if (!is_dir($childBlocksDir)) {
        return;
    }

    // URI tương ứng với child theme root
    $childThemeUri = dirname(get_stylesheet_directory_uri());

    $entries = scandir($childBlocksDir);
    foreach ($entries as $blockName) {
        if ($blockName === '.' || $blockName === '..') {
            continue;
        }

        $blockJson = "{$childBlocksDir}/{$blockName}/block.json";
        if (!file_exists($blockJson)) {
            continue;
        }

        $blockArgs   = [];
        $renderPhp   = "{$childBlocksDir}/{$blockName}/render.php";
        $hasBuild    = is_dir("{$childBlocksDir}/{$blockName}/build");

        if ($hasBuild) {
            $assetFile = "{$childBlocksDir}/{$blockName}/build/index.asset.php";
            $asset     = file_exists($assetFile) ? require $assetFile : ['dependencies' => [], 'version' => null];

            $scriptHandle = 'lacadev-synced-' . $blockName . '-editor';
            $styleHandle  = 'lacadev-synced-' . $blockName;

            $indexJs  = "{$childThemeUri}/block-gutenberg/{$blockName}/build/index.js";
            $indexCss = "{$childThemeUri}/block-gutenberg/{$blockName}/build/index.css";
            $styleCss = "{$childThemeUri}/block-gutenberg/{$blockName}/build/style-index.css";

            wp_register_script($scriptHandle, $indexJs, $asset['dependencies'] ?? [], $asset['version'] ?? null, true);

            if (file_exists("{$childBlocksDir}/{$blockName}/build/index.css")) {
                wp_register_style($scriptHandle, $indexCss, [], $asset['version'] ?? null);
                $blockArgs['editor_style'] = $scriptHandle;
            }

            if (file_exists("{$childBlocksDir}/{$blockName}/build/style-index.css")) {
                wp_register_style($styleHandle, $styleCss, [], $asset['version'] ?? null);
                $blockArgs['style'] = $styleHandle;
            }

            $blockArgs['editor_script'] = $scriptHandle;
        }

        if (file_exists($renderPhp)) {
            $blockArgs['render_callback'] = static function ($attributes, $content) use ($renderPhp) {
                ob_start();
                require $renderPhp;
                return ob_get_clean();
            };
        }

        register_block_type_from_metadata($blockJson, $blockArgs);
    }
}
add_action('init', 'lacadev_child_register_synced_blocks', 15);

/**
 * Expose `image_url` on all public taxonomy terms via the REST API.
 *
 * Supports:
 *  - WooCommerce product_cat (thumbnail_id term meta)
 *  - ACF `term_image` field
 *  - Custom `term_image_url` term meta
 *
 * Usage in edit.js: `term.image_url`
 */
function lacadev_register_term_image_rest_field() {
    $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

    foreach ( $taxonomies as $taxonomy ) {
        register_rest_field(
            $taxonomy,
            'image_url',
            [
                'get_callback' => static function ( $term_data ) {
                    $tid = absint( $term_data['id'] );
                    $tax = $term_data['taxonomy'] ?? '';

                    // ACF
                    if ( function_exists( 'get_field' ) ) {
                        $acf = get_field( 'term_image', $tax . '_' . $tid );
                        if ( $acf ) {
                            return is_array( $acf ) ? $acf['url'] : $acf;
                        }
                    }

                    // Custom meta
                    $url = get_term_meta( $tid, 'term_image_url', true );
                    if ( $url ) {
                        return $url;
                    }

                    // WooCommerce / taxonomy image plugins (thumbnail_id)
                    $thumb_id = get_term_meta( $tid, 'thumbnail_id', true );
                    if ( $thumb_id ) {
                        return wp_get_attachment_image_url( absint( $thumb_id ), 'large' ) ?: '';
                    }

                    return '';
                },
                'schema' => [
                    'description' => 'Term image URL (WooCommerce, ACF, or custom meta)',
                    'type'        => 'string',
                    'readonly'    => true,
                ],
            ]
        );
    }
}
add_action( 'rest_api_init', 'lacadev_register_term_image_rest_field' );

