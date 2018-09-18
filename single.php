<?php
/**
 * The template for displaying all single posts.
 *
 * @package minimalizr
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<div id="index"><!-- #index 600px -->
			<?php while ( have_posts() ) : the_post(); global $post; ?>

				<?php get_template_part( 'template-parts/content', 'single' ); ?>
				
				<?php
					if ( comments_open() || get_comments_number() ) :
						echo '<div class="min-comments">';
						comments_template();
						echo '</div>';
					endif;
				?>

			<?php endwhile; // end of the loop. ?>
		
		</div><!-- #index 600px -->
		
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
