<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme header partial.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">

<head>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<div class="wrapper" data-barba="wrapper">
        <div data-barba="container" data-barba-namespace="default">
            
