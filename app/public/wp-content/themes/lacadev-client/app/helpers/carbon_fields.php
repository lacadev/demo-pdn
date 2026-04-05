<?php
/**
 * Load Carbon Fields.
 *
 * @package WPEmergeCli
 */

/**
 * Bootstrap Carbon Fields container definitions.
 */
function app_bootstrap_carbon_fields_register_fields() {
    include_once APP_APP_SETUP_DIR . 'theme-options.php';
    include_once APP_APP_SETUP_DIR . 'category_meta.php';
}

/**
 * Filter Google Maps API key for Carbon Fields.
 */
function app_filter_carbon_fields_google_maps_api_key() {
    return carbon_get_theme_option('crb_google_maps_api_key');
}
