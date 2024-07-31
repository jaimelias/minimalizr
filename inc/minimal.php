<?php

#[AllowDynamicProperties]
class Minimal_Classes
{
	function __construct()
	{
		add_action('minimal_menu', array(&$this, 'add_menu'));
		add_action('minimal_footer', array(&$this, 'add_footer'));
		add_action('minimal_menu_css', array(&$this, 'add_menu_css'));
	}

	public function add_footer()
	{
		get_footer();
	}

	public function header_args()
	{
		$custom_header = get_custom_header();

		if(substr($custom_header->url, -3) == 'svg')
		{
			$svg = simplexml_load_file(get_attached_file($custom_header->attachment_id)); 
			$svg_attr = $svg->attributes();
			$svg_sizes = explode(' ', $svg_attr->viewBox);
			$custom_header->width = $svg_sizes[2];
			$custom_header->height = $svg_sizes[3];
		}

		return $custom_header;
	}
	
	public function add_menu_css()
	{
		$fontsize = 30;
		$padding = 20;
		$header_args = $this->header_args();
		$logo_height = $header_args->height;
		$sidebarheight = $logo_height+($padding*2);	
		$output = null;
		
		if(!empty(get_header_image()))
		{
			ob_start();
			?>
				<style type="text/css">
					@media screen and (min-width: 1em)
					{
						#minimal-header
						{
						  height: <?php echo $sidebarheight-20; ?>px;
						}
						#minimal-header .site-title, .minimal-menu-bar, .minimal-menu-bar .dashicons, .minimal-side-brand .dashicons
						{
							font-size: <?php echo $fontsize-10; ?>px;					
						}
						.minimal-menu-bar .dashicons, .minimal-side-brand .dashicons
						{
							height: <?php echo $fontsize-10; ?>px;
							width: <?php echo $fontsize; ?>px;
						}
						#minimal-header .site-title
						{
							height: <?php echo $logo_height; ?>px;
							margin: <?php echo $padding-10; ?>px 0 <?php echo $padding-10; ?>px 0;		
						}
						.minimal-menu-bar
						{
							height: <?php echo $fontsize-10; ?>px;
							margin: <?php echo (($sidebarheight-20)-($fontsize-10))/2; ?>px 0 <?php echo (($sidebarheight-20)-($fontsize-10))/2; ?>px 0;
						}
						.minimal-top-menu >.minimal-side-brand a
						{
								padding: <?php echo (($sidebarheight-40)/2)-1; ?>px <?php echo $padding; ?>px <?php echo ($sidebarheight-40)/2; ?>px <?php echo $padding; ?>px;
						}
						body #minimal-wrapper
						{
							margin-top: <?php echo $sidebarheight-20; ?>px;
						}
					}
					@media screen and (min-width: 783px)
					{
						#minimal-header
						{
						  height: <?php echo $sidebarheight; ?>px;
						}
						#minimal-header .site-title, .minimal-menu-bar, .minimal-menu-bar .dashicons, .minimal-side-brand .dashicons
						{
							font-size: <?php echo $fontsize; ?>px;					
						}
						.minimal-menu-bar .dashicons, .minimal-side-brand .dashicons
						{
							height: <?php echo $fontsize; ?>px;
							width: <?php echo $fontsize; ?>px;
						}
						#minimal-header .site-title
						{
							height: <?php echo $logo_height; ?>px;
							margin: <?php echo $padding; ?>px 0 <?php echo $padding; ?>px 0;		
						}
						.minimal-menu-bar
						{
							height: <?php echo $fontsize; ?>px;
							margin: <?php echo (($logo_height+($padding*2))-30)/2; ?>px 0 <?php echo (($logo_height+($padding*2))-30)/2; ?>px 0;
						}			
						.minimal-top-menu >.minimal-side-brand a
						{
								padding: <?php echo (($sidebarheight-20)/2)-1; ?>px <?php echo $padding; ?>px <?php echo ($sidebarheight-20)/2; ?>px <?php echo $padding; ?>px;
						}

						body #minimal-wrapper
						{
							margin-top: <?php echo $sidebarheight; ?>px;
						}
					}
					@media screen and (min-width: 1024px)
					{
						.minimal-navigator
						{
							padding: <?php echo (($sidebarheight-20)/2)+2; ?>px 0;				
						}			
					}		
				</style>					
			<?php
			$output = ob_get_contents();
			ob_end_clean();			
		}
		else
		{
			ob_start();
			?>
				<style type="text/css">
					@media screen and (min-width:1em){#minimal-header{height:50px}#minimal-header .site-title,.minimal-menu-bar{font-size:20px;height:20px;margin:15px 0}.minimal-top-menu>.minimal-side-brand a{padding:14px 15px 15px 15px}body.bodyfull #minimal-wrapper,body:not(.bodyfull) #minimal-wrapper{margin-top:50px}}@media screen and (min-width:783px){#minimal-header{height:70px}#minimal-header .site-title,.minimal-menu-bar{font-size:30px;height:30px;margin:20px 0}.minimal-top-menu>.minimal-side-brand a{padding:24px 25px 25px 25px}body.bodyfull #minimal-wrapper,body:not(.bodyfull) #minimal-wrapper{margin-top:70px}}@media screen and (min-width:1024px){.minimal-navigator{padding:27px 0}}
				</style>			
			<?php
			$output = ob_get_contents();
			ob_end_clean();				
		}
		
		echo $output;
	}
	
	public function add_menu()
	{
		$header_args = $this->header_args();
		$title_tag = (is_front_page()) ? 'h1' : 'h2';
	
		ob_start();
		?>
		<div id="minimal-header" class="clearfix block">
			<div class="pure-g">
				<div class="left pure-u-1 pure-u-sm-1-2 pure-u-md-1-5 pure-u-lg-1-6">
					<div class="pull-left minimal-menu-title">
						<<?php echo esc_html($title_tag); ?> class="site-title">
							<a href="<?php echo esc_url(home_url()); ?>">
								<?php if (empty($header_args->url)): ?>
									<?php echo esc_html(get_bloginfo('name')); ?>
								<?php else: ?>
									<img id="minimal-logo" src="<?php echo esc_url($header_args->url); ?>" 
										 height="<?php echo esc_attr($header_args->height); ?>" 
										 width="<?php echo esc_attr($header_args->width); ?>" 
										 alt="<?php echo esc_attr(get_bloginfo('name')); ?>" />
								<?php endif; ?>
							</a>
						</<?php echo esc_html($title_tag); ?>>
					</div>
				</div>
				
				<div class="right pure-u-1 pure-u-sm-1-2 pure-u-md-4-5 pure-u-lg-5-6">
					<div class="pull-right"> 
						<?php echo $this->responsive(); ?>
						<?php echo $this->minimal(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		echo ob_get_clean();
	}
	
	
	public function minimal()
	{
		return '<div class="minimal-menu-bar pull-right is-closed pointer" data-toggle="offcanvas"><span class="dashicons dashicons-menu"></span></div>';
	}

	public  function responsive()
	{
		wp_nav_menu( array(
			'menu' => 'primary',
			'theme_location' => 'primary',
			'depth' => 2,
			'menu_class' => 'minimal-top-menu',
			'container' => false,
			'items_wrap' => '<nav class="minimal-navigator"><ul class="minimal-top-menu"><li class="minimal-side-brand"><a href="'.home_url().'"><span class="dashicons dashicons-admin-home"></span></a></li>%3$s</ul></nav>',
			'fallback_cb' => 'wp_bootstrap_navwalker::fallback',
			'walker' => new wp_bootstrap_navwalker())
		);
	}
}

$min_classes = new Minimal_Classes();

?>