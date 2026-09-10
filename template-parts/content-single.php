<?php
/**
 * @package minimalizr
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<?php
	$is_full_width = minimalizr_get_meta( 'minimalizr_width' ) === 'full';
	$excerpt       = get_the_excerpt();
	?>

	<?php if ( ! $is_full_width ) : ?>
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title semibold">', '</h1>' ); ?>
			
			<div class="entry-meta small text-muted light bottom-20">
				<?php echo minimalizr_posted_on(); ?>
			</div><!-- .entry-meta -->	
			
			<?php if ( ! empty( $excerpt ) ) : ?>
				<p class="light large">
					<?php echo wp_kses_post( $excerpt ); ?>
				</p>
			<?php endif; ?>	
		</header><!-- .entry-header -->
				
	<?php endif; ?>
	
		

	<div class="<?php echo esc_attr( apply_filters( 'entry_content_class', 'entry-content' ) ); ?>">
		<?php echo apply_filters( 'the_content', get_the_content() ); ?>
	</div><!-- .entry-content -->

	<?php if ( ! $is_full_width ) : ?>
		<footer class="entry-footer">
			<?php minimalizr_entry_footer(); ?>
		</footer><!-- .entry-footer -->
	<?php endif; ?>
</article><!-- #post-## -->


				
