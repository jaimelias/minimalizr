	<?php
	/**
	 * The main template file.
	 *
	 * This is the most generic template file in a WordPress theme
	 * and one of the two required files for a theme (the other being style.css).
	 * It is used to display a page when nothing more specific matches a query.
	 * E.g., it puts together the home page when no home.php file exists.
	 * Learn more: http://codex.wordpress.org/Template_Hierarchy
	 *
	 * @package minimalizr
	 */

	get_header(); ?>
	
				<?php if ( is_home() && ! is_front_page() )
				{
					?>
						<header class="text-center block hero padding-20">
							<h1 class="page-title"><?php single_post_title(); ?></h1>
						</header>				
					<?php
				}
				?>	
	
<div class="pure-g gutters">	

	<div class="pure-u-1 pure-u-md-2-3">
		<div id="primary" class="content-area">
			<main id="main" class="site-main">

			<div class="minimal-site-alert" data-nosnippet><?php do_action('minimal_site_alert'); ?></div>
			
			<?php if ( have_posts() ) : ?>

				<div id="index">
					<?php while ( have_posts() ){ 
							the_post(); 
							get_template_part( 'template-parts/content', get_post_format() );
						} 
						wp_reset_query(); 
					?>
				</div>

				<?php the_posts_navigation(); ?>

			<?php else : ?>

				<?php get_template_part( 'template-parts/content', 'none' ); ?>

			<?php endif; ?>

			</main><!-- #main -->
		</div><!-- #primary -->
	</div><!-- .pure-u-1 -->
	<div class="pure-u-1 pure-u-md-1-3">
		<?php if ( is_active_sidebar( 'sidebar-6' ) ) : ?>
			<aside id="sidebar">
				<?php dynamic_sidebar( 'sidebar-6' ); ?>
			</aside>
		<?php endif; ?>	
	</div>
</div><!-- .pure-g -->

	<?php do_action('minimal_footer'); ?>
