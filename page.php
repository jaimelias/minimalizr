<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package minimalizr
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main">

			<div class="minimal-site-alert" data-nosnippet><?php do_action('minimal_site_alert'); ?></div>
		
			<?php if(is_active_sidebar("sidebar-5") && minimalizr_get_meta( "minimalizr_width" ) != "100%" ): ?>
			<div class="pure-g">
				<div class="pure-u-1 pure-u-sm-1-1 pure-u-md-3-5">
			<?php endif; ?>
			
			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'template-parts/content', 'page' ); ?>

				<?php
					// If comments are open or we have at least one comment, load up the comment template
					if ((is_page()) && (comments_open() || get_comments_number())) :
						comments_template();
					endif;
				?>

			<?php endwhile; // end of the loop. ?>

			<?php if(is_active_sidebar("sidebar-5") && minimalizr_get_meta( "minimalizr_width" ) != "100%" ): ?>
			</div>
				<div class="pure-u-1 pure-u-sm-1-1 pure-u-md-1-4">
					<div class="rightsidebar"><?php dynamic_sidebar("sidebar-5"); ?></div>
				</div>
			</div>	
			<?php endif; ?>			

		</main><!-- #main -->
	</div><!-- #primary -->

<?php do_action('minimal_footer'); ?>
