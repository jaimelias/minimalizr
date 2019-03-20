<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package minimalizr
 */
?>


<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<?php if(is_front_page()): ?>

	<?php else: ?>
	<header class="entry-header">
	<?php 
	
		if(in_array('bodyfixed', get_body_class()) || (!in_array('bodyfixed', get_body_class()) && !in_array('bodyfull', get_body_class())))
		{
			$this_title = get_the_title();
			
			if(is_tax())
			{
				$this_title = html_entity_decode($this_title);
			}
			
			echo '<h1 class="entry-title">'.$this_title.'</h1>';
			
			if(has_excerpt())
			{
				if(is_singular() && strlen(get_the_excerpt()) > 0)
				{
					echo '<p itemprop="description" class="large bottom-10">'.get_the_excerpt().'</p><hr/>';
				}
				if(is_tax() && strlen(term_description()) > 0)
				{
					echo '<p itemprop="description" class="large bottom-10">'.esc_html(get_term(get_queried_object()->term_id)->description).'</p><hr />';
				}
			}			
		}	
	?>
	</header><!-- .entry-header -->
	<?php endif; ?>


	<div class="entry-content">
		<?php the_content(); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'minimalizr' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

</article><!-- #post-## -->
