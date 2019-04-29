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
				<?php minimalizr_posted_on(); ?>
			</div><!-- .entry-meta -->	
			
			<?php if(get_the_excerpt()) : ?>
				<h2 class="light small">
					<?php echo get_the_excerpt(); ?>
				</h2>
			<?php endif; ?>
		
			
				<?php if ( has_post_thumbnail() ) : ?>
					<p><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
					<?php the_post_thumbnail('medium', array('class' => 'img-responsive')); ?>
					</a></p>
				<?php endif; ?>		
		</header><!-- .entry-header -->
				
	<?php endif; ?>
	
		

	<div class="entry-content">
		<?php the_content(); ?>
	</div><!-- .entry-content -->

	<?php if(minimalizr_get_meta( "minimalizr_width" ) != 'full'): ?>
		<footer class="entry-footer">
			<?php minimalizr_entry_footer(); ?>
		</footer><!-- .entry-footer -->
	<?php endif; ?>
</article><!-- #post-## -->


				
