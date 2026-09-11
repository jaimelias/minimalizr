<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @package minimalizr
 */

dy_errors::add( __( 'Page Not Found', 'minimalizr' ), 404 );

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<?php do_action('minimal_site_alert'); ?>

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php echo esc_html(__( 'Error 404' , 'minimalizr')); ?></h1>
				</header>

				<div class="page-content">
					<?php echo wp_kses_post( apply_filters( 'the_content', '' ) ); ?>
				</div>
			</section>

		</main>
	</div>

<?php do_action( 'minimal_footer' ); ?>
