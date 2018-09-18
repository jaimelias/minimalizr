<?php

class Minimal_Classes
{
	
	public static function top_menu()
	{
		$menu = Minimal_Classes::responsive();	
		$menu .= Minimal_Classes::minimal();
		echo $menu;
	}
	
	public static function add_class()
	{
		if(wp_is_mobile())
		{
			$class = 'minimal';
		}
		else
		{
			if(get_theme_mod('minimalizr_menu') == '' || get_theme_mod('minimalizr_menu') == 'minimal')
			{
				$class = 'minimal';
			}
			else
			{
				$class = 'responsive';	
			}			
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
			'items_wrap'      => '<nav class="top_navigator"><ul class="top_menu"><li class="sidebar-brand"><a href="'.esc_url(home_url()).'"><i class="fas fa-home"></i> '.__("Home", "minimalizr").'</a></li>%3$s</ul></nav>',
			'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
			'walker'            => new wp_bootstrap_navwalker())
		);
	}
	
}

?>