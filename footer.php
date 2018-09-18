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
		
		<?php get_sidebar(); ?>

		
			<div class="smcontainer large">
		<?php 
				$media = array("facebook", "twitter", "linkedin", "youtube", "instagram", "pinterest", "google-plus");
				
				for($x = 0; $x < count($media); $x++)
				{
						if(get_theme_mod($media[$x]) != null)
						{
							if(!filter_var(get_theme_mod($media[$x]), FILTER_VALIDATE_URL) === false)
							{
								echo '<a id="mn'.esc_html($media[$x]).'" target="_blank" class="smbutton" href="'.esc_url(get_theme_mod($media[$x])).'"><i class="fab fa-'.esc_html($media[$x]).'"></i></a>';
							}
						}	
				}
		
		?></div><!-- .smcontainer -->
				
		<div class="site-info text-center clearfix">
		<i class="fa fa-copyright"></i> <span><?php echo esc_html(date('Y')); ?></span> <?php echo esc_html(get_bloginfo('name')); ?>
		</div><!-- .site-info -->
		</footer><!-- #footer -->	

	
</div><!-- #page-content-wrapper -->

<div class="overlay"></div>

</div><!-- #wrapper -->

<div id="datepicker-container"></div>
<div id="timepicker-container"></div>

<?php wp_footer(); ?>

</body>
</html>
