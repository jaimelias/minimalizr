<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package minimalizr
 */
?>


<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<header class="entry-header">
	<?php 
	
		$output = null;
		$title = (is_tax()) ? html_entity_decode(get_the_title()) : get_the_title();
		$title = (is_front_page()) ?  '<h2 class="entry-title">'.$title.'</h2>' : '<h1 class="entry-title">'.$title.'</h1>';
		$description = null;
		
		if(has_excerpt())
		{
			if(is_singular() && strlen(get_the_excerpt()) > 0)
			{
				$description = '<p itemprop="description" class="large bottom-10">'.get_the_excerpt().'</p>';
				
				if(!in_array('bodyfull', get_body_class()))
				{
					$description .= '<hr/>';
				}
			}
			if($description && strlen(term_description()) > 0)
			{
				$description = '<p itemprop="description" class="large bottom-10">'.esc_html(get_term(get_queried_object()->term_id)->description).'</p>';
			}
		}

		
		if(in_array('bodyfull', get_body_class()))
		{
			echo '<div class="minimal-box text-center"><div class="container">'.$title.$description.'</div></div>';
		}
		else
		{
			echo $title.$description;
		}

	?>
	</header><!-- .entry-header -->
	

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
