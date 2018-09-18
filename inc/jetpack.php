<?php
/**
 * Jetpack Compatibility File
 * See: https://jetpack.me/
 *
 * @package minimalizr
 */

/**
 * Add theme support for Infinite Scroll.
 * See: https://jetpack.me/support/infinite-scroll/
 */
function minimalizr_jetpack_setup() {
	add_theme_support( 'infinite-scroll', array(
		'container' => 'main',
		'render'    => 'minimalizr_infinite_scroll_render',
		'footer'    => 'page',
	) );
} // end function minimalizr_jetpack_setup
add_action( 'after_setup_theme', 'minimalizr_jetpack_setup' );

function minimalizr_infinite_scroll_render() {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content', get_post_format() );
	}
} // end function minimalizr_infinite_scroll_render