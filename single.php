<?php
/**
 * The template for displaying all single posts.
 *
 * @package minimalizr
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			
			<div class="minimal-site-alert" data-nosnippet><?php do_action('minimal_site_alert'); ?></div>
			
			<div id="index"><!-- #index 600px -->
				<?php while ( have_posts() ) : the_post(); global $post; ?>

					<?php get_template_part( 'template-parts/content', 'single' ); ?>

				<?php endwhile; // end of the loop. ?>
			
			</div><!-- #index 600px -->
		
		</main><!-- #main -->
	</div><!-- #primary -->

<?php do_action('minimal_footer'); ?>
