<?php

class Minimal_Classes
{
	function __construct()
	{
		add_action('minimal_menu', array('Minimal_Classes', 'add_menu'));
		add_action('minimal_menu_css', array('Minimal_Classes', 'add_menu_css'));
	}
	
	public static function add_menu_css()
	{
		$fontsize = 30;
		$padding = 20;
		$logoheight = minimalizr_header()->height;
		$sidebarheight = $logoheight+($padding*2);	
		$output = null;
		
		if(get_header_image() != '')
		{
			ob_start();
			?>
				<style type="text/css">
					@media screen and (min-width: 1em)
					{
						.minimal-header
						{
						  height: <?php echo $sidebarheight-20; ?>px;
						}
						.minimal-header .site-title, .minimal-menu-bar
						{
							font-size: <?php echo $fontsize-10; ?>px;					
						}
						.minimal-header .site-title
						{
							height: <?php echo $logoheight; ?>px;
							margin: <?php echo $padding-10; ?>px 0 <?php echo $padding-10; ?>px 0;		
						}
						.minimal-menu-bar
						{
							height: <?php echo $fontsize-10; ?>px;
							margin: <?php echo (($sidebarheight-20)-($fontsize-10))/2; ?>px 0 <?php echo (($sidebarheight-20)-($fontsize-10))/2; ?>px 0;
						}
						.minimal .top_menu >.sidebar-brand a, .responsive .top_menu >.sidebar-brand a
						{
								padding: <?php echo (($sidebarheight-40)/2)-1; ?>px <?php echo $padding; ?>px <?php echo ($sidebarheight-40)/2; ?>px <?php echo $padding; ?>px;
						}
						body:not(.bodyfull) #wrapper, body.bodyfull #wrapper
						{
							margin-top: <?php echo $sidebarheight-20; ?>px;
						}
					}
					@media screen and (min-width: 768px)
					{
						.minimal-header
						{
						  height: <?php echo $sidebarheight; ?>px;
						}
						.minimal-header .site-title, .minimal-menu-bar
						{
							font-size: <?php echo $fontsize; ?>px;					
						}
						.minimal-header .site-title
						{
							height: <?php echo $logoheight; ?>px;
							margin: <?php echo $padding; ?>px 0 <?php echo $padding; ?>px 0;		
						}
						.minimal-menu-bar
						{
							height: <?php echo $fontsize; ?>px;
							margin: <?php echo (($logoheight+($padding*2))-30)/2; ?>px 0 <?php echo (($logoheight+($padding*2))-30)/2; ?>px 0;
						}			
						.minimal .top_menu >.sidebar-brand a, .responsive .top_menu >.sidebar-brand a
						{
								padding: <?php echo (($sidebarheight-20)/2)-1; ?>px <?php echo $padding; ?>px <?php echo ($sidebarheight-20)/2; ?>px <?php echo $padding; ?>px;
						}

						body:not(.bodyfull) #wrapper, body.bodyfull #wrapper
						{
							margin-top: <?php echo $sidebarheight; ?>px;
						}
					}
					@media screen and (min-width: 1024px)
					{
						.responsive .top_navigator
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
					@media screen and (min-width:1em){.minimal-header{height:50px}.minimal-header .site-title,.minimal-menu-bar{font-size:20px;height:20px;margin:15px 0}.minimal .top_menu>.sidebar-brand a,.responsive .top_menu>.sidebar-brand a{padding:14px 15px 15px 15px}body.bodyfull #wrapper,body:not(.bodyfull) #wrapper{margin-top:50px}}@media screen and (min-width:768px){.minimal-header{height:70px}.minimal-header .site-title,.minimal-menu-bar{font-size:30px;height:30px;margin:20px 0}.minimal .top_menu>.sidebar-brand a,.responsive .top_menu>.sidebar-brand a{padding:24px 25px 25px 25px}body.bodyfull #wrapper,body:not(.bodyfull) #wrapper{margin-top:70px}}@media screen and (min-width:1024px){.responsive .top_navigator{padding:27px 0}}
				</style>			
			<?php
			$output = ob_get_contents();
			ob_end_clean();				
		}
		
		echo $output;
	}
	
	public static function add_menu()
	{
		ob_start();
		?>
		<div class="minimal-header clearfix block">
			<div class="pure-g">
				<div class="left pure-u-1 pure-u-sm-1-2 pure-u-md-1-5 pure-u-lg-1-6">
					<div class="pull-left minimal-menu-title">
						<?php if(is_front_page()) { $titletag = "h1"; } else { $titletag = "h2"; } ?>
						<<?php echo $titletag; ?> class="site-title">
						<a href="<?php echo esc_url(home_url()); ?>">
							<?php if(get_header_image() == ''): ?>
								<?php echo esc_html( get_bloginfo('name') ); ?>
							<?php else: ?>
								<img src="<?php esc_url(header_image()); ?>" height="<?php echo esc_html(minimalizr_header()->height); ?>" width="<?php echo esc_html(minimalizr_header()->width); ?>" alt="<?php esc_html(bloginfo('name')); ?>" />
							<?php endif; ?>
						</a></<?php echo $titletag; ?>>
					</div>
				</div>
				
				<div class="right pure-u-1 pure-u-sm-1-2 pure-u-md-4-5 pure-u-lg-5-6">
					<div class="pull-right <?php Minimal_Classes::add_class(); ?>"> 
						<?php Minimal_Classes::top_menu(); ?>
					</div>
				</div>

			</div>

		</div>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		echo $output;	
	}
	public static function top_menu()
	{
		$menu = Minimal_Classes::responsive();	
		
		if(has_nav_menu('primary'))
		{
			$menu .= Minimal_Classes::minimal();
		}
		
		echo $menu;
	}
	
	public static function add_class()
	{
		if(get_theme_mod('minimalizr_menu') == '' || get_theme_mod('minimalizr_menu') == 'minimal')
		{
			$class = 'minimal';
		}
		else
		{
			$class = 'responsive';	
		}
		echo $class;			
	}
	
	public static function minimal()
	{
		return '<span class="minimal-menu-bar pull-right is-closed pointer" data-toggle="offcanvas"><i class="fas fa-bars"></i></span>';
	}

	public static function responsive()
	{
		wp_nav_menu( array(
			'menu'              => 'primary',
			'theme_location'    => 'primary',
			'depth'             => 2,
			'menu_class'        => 'top_menu',
			'container' => false,
			'items_wrap'      => '<nav class="top_navigator"><ul class="top_menu"><li class="sidebar-brand"><a href="'.esc_url(home_url()).'"><i class="fas fa-home"></i></a></li>%3$s</ul></nav>',
			'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
			'walker'            => new wp_bootstrap_navwalker())
		);
	}
}

$min_classes = new Minimal_Classes();

?>