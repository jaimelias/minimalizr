<?php
/**
 * @package minimalizr
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="pure-g gutters">
				<div class="pure-u-1-3">
					
						<p><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail('medium', array('class' => 'img-responsive')); ?>
						</a></p>
					
					<div class="entry-meta text-muted small light bottom-10">
						<?php echo apply_filters('minimal_posted_on', minimalizr_posted_on()); ?>
					</div><!-- .entry-meta -->					
				</div>		
				<div class="pure-u-2-3">		
					<?php the_title( sprintf( '<h3 class="entry-title"><a class="normal" href="%s">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
					<div class="<?php echo apply_filters('entry_content_class', 'entry-content'); ?> normal small">
						<?php echo apply_filters('minimal_archive_excerpt', get_the_excerpt()); ?>
					</div><!-- .entry-content -->
				</div>
			</div>
		<?php else: ?>
			<?php the_title( sprintf( '<h3 class="entry-title"><a class="normal" href="%s">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
			<div class="<?php echo apply_filters('entry_content_class', 'entry-content'); ?> normal small">
				<?php echo apply_filters('minimal_archive_excerpt', get_the_excerpt()); ?>
			</div><!-- .entry-content -->		
		<?php endif; ?>
	</header><!-- .entry-header -->

</article><!-- #post-## -->