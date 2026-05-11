<?php
/**
 * Script Governance
 *
 * Dequeues plugin/theme assets that are globally enqueued but only needed on
 * specific page types.  Add rules here whenever a plugin loads heavy JS/CSS
 * site-wide but is only used in one section.
 *
 * @package LacaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

use App\Performance\ScriptGovernor;

add_action('wp', function () {
    $gov = new ScriptGovernor();

    // ── Contact Form 7 ───────────────────────────────────────────────────
    // CF7 loads scripts/styles on every page. Only enqueue on pages that
    // actually use a CF7 shortcode or have the contact-page template.
    $gov
        ->dequeueScript('contact-form-7', function () {
            if (is_page_template('page-contact.php')) {
                return false; // keep
            }
            // Also keep on any page with a CF7 shortcode in its content.
            global $post;
            if ($post && has_shortcode($post->post_content, 'contact-form-7')) {
                return false; // keep
            }
            return true; // remove everywhere else
        })
        ->dequeueStyle('contact-form-7', function () {
            if (is_page_template('page-contact.php')) {
                return false;
            }
            global $post;
            if ($post && has_shortcode($post->post_content, 'contact-form-7')) {
                return false;
            }
            return true;
        });

    // ── WooCommerce ──────────────────────────────────────────────────────
    // WC loads a large CSS bundle globally; restrict to WC pages only.
    $gov
        ->dequeueStyle('woocommerce-general', fn() => !ScriptGovernor::isWooPage())
        ->dequeueStyle('woocommerce-layout', fn() => !ScriptGovernor::isWooPage())
        ->dequeueStyle('woocommerce-smallscreen', fn() => !ScriptGovernor::isWooPage())
        ->dequeueScript('wc-cart-fragments', fn() => !ScriptGovernor::isWooPage());

    // ── Gravity Forms ────────────────────────────────────────────────────
    $gov
        ->dequeueScript('gform_gravityforms', function () {
            global $post;
            if ($post && (has_shortcode($post->post_content, 'gravityforms') || has_shortcode($post->post_content, 'gravityform'))) {
                return false;
            }
            return true;
        })
        ->dequeueStyle('gforms_css', function () {
            global $post;
            if ($post && (has_shortcode($post->post_content, 'gravityforms') || has_shortcode($post->post_content, 'gravityform'))) {
                return false;
            }
            return true;
        });

    // ── Elementor ────────────────────────────────────────────────────────
    // Only load Elementor frontend assets on pages built with Elementor.
    $gov
        ->dequeueStyle('elementor-frontend', function () {
            global $post;
            if ($post && get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
                return false;
            }
            return true;
        });

    // ── Rank Math / Yoast breadcrumbs inline CSS ─────────────────────────
    // These are usually tiny but can be removed if you handle breadcrumbs yourself.
    // Uncomment to activate:
    // $gov->dequeueStyle('rank-math', fn() => true);

    $gov->boot();
}, 5); // priority 5 on wp hook — before wp_enqueue_scripts fires
