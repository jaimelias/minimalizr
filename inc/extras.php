<?php
/**
 * Custom functions that act independently of the theme templates
 *
 * Eventually, some of the functionality here could be replaced by core features
 *
 * @package minimalizr
 */

add_filter( 'document_title_separator', 'modify_separator', 100);

function modify_separator(): string
{
	return '|';
}

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array<int, string> $classes Classes for the body element.
 * @return array<int, string>
 */
function minimalizr_body_classes( array $classes ): array {
	
	
	if ( is_multi_author() ) {
		$classes[] = 'group-blog';
	}
	if(is_front_page())
	{
		$classes[] = 'front-page';
	}
	$layout = minimalizr_get_meta( 'minimalizr_width' );

	if ( $layout )
	{
		if ( $layout === 'full' && ( is_page() || is_single() ) )
		{
			$classes[] = 'bodyfull';
		}

		else
		{
			$classes[] = 'bodyfixed';
		}
	}

	if ( ! is_singular( 'post' ) )
	{
		$blog_key = array_search( 'blog', $classes, true );

		if ( false !== $blog_key ) {
			unset( $classes[ $blog_key ] );
		}
	}


	return $classes;
}
add_filter( 'body_class', 'minimalizr_body_classes', 100 );
