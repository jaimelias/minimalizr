<?php
/**
 * @package minimalizr
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

		<?php if ( 'post' == get_post_type() ) : ?>
		<div class="entry-meta  small light bottom-20">
			<?php minimalizr_posted_on(); ?>
		</div><!-- .entry-meta -->
		
			<?php if ( has_post_thumbnail() ) : ?>
				<p><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
				<?php the_post_thumbnail('medium', array('class' => 'img-responsive')); ?>
				</a></p>
			<?php endif; ?>
			
		<?php endif; ?>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php the_excerpt();?>
		<p><a class="pure-button button-warning strong" title="<?php the_title(); ?>" href="<?php the_permalink(); ?>"><?php _e('Continue Reading', 'minimalizr'); ?></a></p>
	</div><!-- .entry-content -->

	<hr />
		
	<footer class="entry-footer">
		<div class="pure-g">
			<div class="pure-u-3-4">
				<?php minimalizr_sharethis(); ?>
			</div>		
			<div class="pure-u-1-4 strong">
				<div class="large clearfix">
					<span class="pull-right">
						<span class="inline-block"><span class="disqus-comment-count" data-disqus-url="<?php the_permalink(); ?>">0</span></span> <i class="fas fa-comments"></i>
					</span>
				</div>
			</div>
		</div>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->