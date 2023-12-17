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


if ( version_compare( $GLOBALS['wp_version'], '4.1', '<' ) ) :
	/**
	 * Filters wp_title to print a neat <title> tag based on what is being viewed.
	 *
	 * @param string $title Default title text for current view.
	 * @param string $sep Optional separator.
	 * @return string The filtered title.
	 */
	function minimalizr_wp_title( $title, $sep ) {
		if ( is_feed() ) {
			return $title;
		}

		global $page, $paged;

		// Add the blog name
		$title .= get_bloginfo( 'name', 'display' );

		// Add the blog description for the home/front page.
		$site_description = get_bloginfo( 'description', 'display' );
		if ( $site_description && ( is_home() || is_front_page() ) ) {
			$title .= " $sep $site_description";
		}

		// Add a page number if necessary:
		if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) {
			$title .= " $sep " . sprintf( esc_html__( 'Page %s', 'minimalizr' ), max( $paged, $page ) );
		}

		return $title;
	}
	
	add_filter( 'wp_title', 'minimalizr_wp_title', 10, 2 );


	/**
	 * Title shim for sites older than WordPress 4.1.
	 *
	 * @link https://make.wordpress.org/core/2014/10/29/title-tags-in-4-1/
	 * @todo Remove this function when WordPress 4.3 is released.
	 */
	function minimalizr_render_title() {
		?>
		<title><?php wp_title( '|', true, 'right' ); ?></title>
		<?php
	}
	add_action( 'wp_head', 'minimalizr_render_title' );
	
	
endif;
