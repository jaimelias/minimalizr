<?php
/**
 * @package minimalizr
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
	
		<div class="pure-g gutters">
			<div class="pure-u-1-3">
				<?php if ( has_post_thumbnail() ) : ?>
					<p><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
					<?php the_post_thumbnail('medium', array('class' => 'img-responsive')); ?>
					</a></p>
				<?php endif; ?>
				<div class="entry-meta text-muted small light bottom-10">
					<?php minimalizr_posted_on(); ?>
				</div><!-- .entry-meta -->					
			</div>		
			<div class="pure-u-2-3">		
				<?php the_title( sprintf( '<h2 class="entry-title small light uppercase"><a class="small normal" href="%s">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
				<div class="entry-content normal small">
					<?php the_excerpt();?>
				</div><!-- .entry-content -->
			</div>
		</div>
	</header><!-- .entry-header -->


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