<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package minimalizr
 */
?>

	</div><!-- #content -->	

		<footer id="footer" class="clearfix">

		<?php 
				get_sidebar();

				$media = ["facebook", "twitter", "linkedin", "youtube", "instagram", "pinterest", "google", "tiktok"];
				$svg_icons = ["tiktok"];
				$min_sm_btn = '';
				
				for($x = 0; $x < count($media); $x++)
				{
					$key = $media[$x];
					$mod = get_theme_mod($key);

					if(
						!$mod
						|| filter_var($mod, FILTER_VALIDATE_URL) === false	
					) continue;

					$icon_type = in_array($key, $svg_icons, true) 
						? 'svgicons svgicons' 
						: 'dashicons dashicons';


					$min_sm_btn .= sprintf(
						'<a id="mn%s" target="_blank" class="smbutton" href="%s"><span class="%s-%s"></span></a>',
						esc_attr($key),
						esc_url($mod),
						esc_attr($icon_type),
						esc_attr($key)
					);
				}
		
		?>
		
		<?php if($min_sm_btn != ''): ?>
			<div class="smcontainer large">
				<?php echo $min_sm_btn; ?>
			</div><!-- .smcontainer -->
		<?php endif; ?>
		
		
				
		<div class="site-info semibold text-center clearfix">
		&#169; <span><?php echo esc_html(date('Y')); ?></span> <?php echo esc_html(get_bloginfo('name')); ?>
		</div><!-- .site-info -->
		</footer><!-- #footer -->	

	
</div><!-- #page-content-wrapper -->

<div class="overlay"></div>

</div><!-- #minimal-wrapper -->



<?php wp_footer(); ?>

<div class="minimal-footer-alert" data-nosnippet><?php do_action('minimal_footer_alert'); ?></div>

</body>
</html>
