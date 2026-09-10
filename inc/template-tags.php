<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package minimalizr
 */


if ( ! function_exists( 'minimalizr_posted_on' ) ) :
/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function minimalizr_posted_on() {

	return '<span class="posted-on hidden">' . esc_html(get_the_date()) . '</span><span class="byline"> '. esc_html(__('by', 'minimalizr')).' <span class="normal">'.get_the_author() . '</span></span>';

}
endif;

if ( ! function_exists( 'minimalizr_entry_footer' ) ) :
/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function minimalizr_entry_footer() {
	// Hide category and tag text for pages.
	if ( 'post' == get_post_type() ) {
		/* translators: used between list items, there is a space after the comma */
		$categories_list = get_the_category_list( esc_html__( ', ', 'minimalizr' ) );
		if ( $categories_list ) {
			printf( '<hr/><div class="cat-links">' . esc_html__( 'Posted in %1$s', 'minimalizr' ) . '</div>', $categories_list ); // WPCS: XSS OK
		}

		$iconlabel = '<span class="dashicons dashicons-tag"></span>';
		$tags_list = get_the_tag_list( '<span class="entry-tag">'.$iconlabel, '</span><span class="entry-tag">'.$iconlabel, '</span>');
		if ( $tags_list ) {
			printf( '<hr/><div class="tags-links">' . esc_html__( 'Tags %1$s', 'minimalizr' ) . '</div>', $tags_list ); // WPCS: XSS OK
		}
	}
}
endif;
