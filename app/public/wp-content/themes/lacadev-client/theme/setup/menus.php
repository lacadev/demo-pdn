<?php
/**
 * Register menu locations.
 *
 * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
 *
 * @hook    after_setup_theme
 * @package WPEmergeTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Carbon_Fields\Container;
use Carbon_Fields\Field;

register_nav_menus(
[
		'main-menu' => __( 'Main Menu', 'laca' ),
    'footer-menu' => __( 'Footer Menu', 'laca' ),
	]
);

/**
 * Create custom menu metaz
 */
Container::make('nav_menu_item', __('Cài dặt mở rộng'))
  ->add_fields([
      Field::make('image', 'menu_img', __('Menu image', 'laca')),
  ]);

/**
 * Remove menu item IDs and simplify classes globally
 */
add_filter('nav_menu_item_id', '__return_empty_string');

/**
 * Clean up default classes (optional, since walker handles it, 
 * but useful for other menus)
 */
add_filter('nav_menu_css_class', function ($classes, $item, $args, $depth) {
    if (!is_array($classes)) return $classes;

    $is_active = !empty(array_intersect($classes, [
        'actived-menu',
        'current-menu-parent',
        'current-menu-ancestor',
        'current_page_item',
        'current_page_parent',
        'current_page_ancestor',
    ]));

    $result = [];

    if ($is_active) {
        $result[] = 'actived-menu';
    }

    if (in_array('menu-item-has-children', $classes, true)) {
        $result[] = 'menu-item-has-children';
    }

    return $result;
}, 10, 4);
