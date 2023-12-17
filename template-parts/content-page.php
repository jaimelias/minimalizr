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
	
		$is_tax = is_tax();
		$title = ($is_tax) ? html_entity_decode(get_the_title()) : get_the_title();
		$title = (is_front_page()) ?  '<h2 class="entry-title">'.$title.'</h2>' : '<h1 class="entry-title">'.$title.'</h1>';
		$description = '';
		
		if(is_singular() && has_excerpt())
		{
			$excerpt = get_the_excerpt();

			if(!empty($excerpt))
			{
				$description = '<p itemprop="description" class="large bottom-10">'.$excerpt.'</p>';
				
				if(!in_array('bodyfull', get_body_class()))
				{
					$description .= '<hr/>';
				}
			}
		}

		if($is_tax)
		{
			if(!empty(term_description()))
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
		<?php echo apply_filters( 'the_content', get_the_content() ); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'minimalizr' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

</article><!-- #post-## -->
