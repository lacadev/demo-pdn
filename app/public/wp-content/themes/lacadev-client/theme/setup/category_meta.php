<?php
/**
 * Category custom meta fields.
 *
 * @package WPEmergeTheme
 */

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;

if (!defined('ABSPATH')) {
    exit;
}

Container::make('term_meta', __('Layout Settings', 'laca'))
    ->where('term_taxonomy', 'IN', array('category', 'blog_cat'))
    ->add_fields(array(
        Field::make('select', 'crb_archive_layout', __('Archive Layout', 'laca'))
            ->set_options(array(
                'card' => __('Card Layout (Bản tin)', 'laca'),
                'staggered' => __('Staggered Layout (So le)', 'laca'),
            ))
            ->set_default_value('card'),
    ));
