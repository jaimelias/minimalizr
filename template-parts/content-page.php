<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package minimalizr
 */

	$is_tax        = is_tax();
	$is_full_width = minimalizr_get_meta( 'minimalizr_width' ) === 'full' && ( is_page() || is_single() );
	$title         = '';
	$description   = '';

	if ( ! $is_full_width ) {
		$title = $is_tax ? html_entity_decode( get_the_title() ) : get_the_title();
		$title = is_front_page() ? '<h2 class="entry-title">' . $title . '</h2>' : '<h1 class="entry-title">' . $title . '</h1>';

		if ( is_singular() && has_excerpt() ) {
			$excerpt = get_the_excerpt();

			if ( ! empty( $excerpt ) ) {
				$description = '<p itemprop="description" class="large bottom-10">' . $excerpt . '</p><hr/>';
			}
		}

		if ( $is_tax && ! empty( term_description() ) ) {
			$description = '<p itemprop="description" class="large bottom-10">' . esc_html( get_term( get_queried_object()->term_id )->description ) . '</p>';
		}
	}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	
	<?php

		if ( ! $is_full_width )
		{
			echo wp_kses_post( sprintf( '<header class="entry-header">%s%s</header>', $title, $description ) );
		}

	?>
	


	<div class="<?php echo esc_attr( apply_filters( 'entry_content_class', 'entry-content' ) ); ?>">
		<?php echo apply_filters( 'the_content', get_the_content() ); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'minimalizr' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

</article><!-- #post-## -->
