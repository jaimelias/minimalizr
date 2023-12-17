<?php
/**
 * @package minimalizr
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<?php if(minimalizr_get_meta( "minimalizr_width" ) != 'full'): ?>
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title semibold">', '</h1>' ); ?>
			
			<div class="entry-meta small text-muted light bottom-20">
				<?php echo minimalizr_posted_on(); ?>
			</div><!-- .entry-meta -->	
			
			<?php if(get_the_excerpt()) : ?>
				<p class="light large">
					<?php echo get_the_excerpt(); ?>
				</p>
			<?php endif; ?>	
		</header><!-- .entry-header -->
				
	<?php endif; ?>
	
		

	<div class="entry-content">
		<?php echo apply_filters( 'the_content', get_the_content() ); ?>
	</div><!-- .entry-content -->

	<?php if(minimalizr_get_meta( "minimalizr_width" ) != 'full'): ?>
		<footer class="entry-footer">
			<?php minimalizr_entry_footer(); ?>
		</footer><!-- .entry-footer -->
	<?php endif; ?>
</article><!-- #post-## -->


				
