<?php
/**
 * Custom functions that act independently of the theme templates
 *
 * Eventually, some of the functionality here could be replaced by core features
 *
 * @package minimalizr
 */

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
 
add_filter( 'document_title_separator', 'modify_separator', 100);

function modify_separator()
{
	return '|';
}

 
function minimalizr_body_classes( $classes ) {
	
	
	if ( is_multi_author() ) {
		$classes[] = 'group-blog';
	}
	if(is_front_page())
	{
		$classes[] = 'front-page';
	}
	if(minimalizr_get_meta( "minimalizr_width" ))
	{
		if(is_page() && is_front_page()) {
			$classes[] = 'bodyfull';
		}
		if(minimalizr_get_meta( "minimalizr_width" ) === "full" && (is_page() || is_single()))
		{
			$classes[] = 'bodyfull';
		}

		else
		{
			$classes[] = 'bodyfixed';
		}
	}

	if(!is_singular('post'))
	{
		unset($classes[array_search('blog', $classes)]);
	}


	return $classes;
}
add_filter( 'body_class', 'minimalizr_body_classes', 100 );

?>