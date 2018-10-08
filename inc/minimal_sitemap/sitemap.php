<?php 

class minimal_sitemap{
	
	
	public static function headers()
	{
		if(isset($_GET['sitemap']))
		{
			header('Content-type: application/xml');
		}
	}
	public static function run($template)
	{
		if(isset($_GET['sitemap']))
		{
			$post_type = sanitize_text_field($_GET['sitemap']);
			
			if($post_type == '')
			{
				$post_type = 'page,post';
			}
			
			$posts_per_page = 200;
			$args = array();
			$args['post_type'] = explode(",", $post_type);			
			$args['posts_per_page'] = $posts_per_page;
			
			//WPML fix all languages
			$args['suppress_filters'] = true;
			
			//Polylang fix all languages
			if(minimal_sitemap::polylang())
			{
				$args['lang'] = minimal_sitemap::polylang();
			}
			
			$sitemap_query = new WP_Query($args);
			$output = '';
			
			if($sitemap_query->have_posts())
			{
				ob_start();
				
				?>
				<?xml version="1.0" encoding="UTF-8"?>
				<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
					xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0"
					xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
				<?php
				
				while ($sitemap_query->have_posts())
				{
					$sitemap_query->the_post();
					global $post;
					?>
					<url>
					<loc><?php echo esc_url(get_permalink()); ?></loc>
					
					<?php if(has_post_thumbnail()): ?>
						<image:image>
							<image:loc><?php echo esc_url(get_the_post_thumbnail_url($post->ID,'full')); ?></image:loc>
						</image:image>
					<?php endif; ?>
					
					<changefreq><?php echo esc_html(minimal_sitemap::changefreq($post)); ?></changefreq>
					<mobile:mobile/>
					</url>
					<?php
				}
				
				?>
				</urlset>
				<?php
				
				$content = ob_get_contents();
				ob_end_clean();
				$output = $content;
			}
			wp_reset_query();
			exit(ent2ncr(minimal_sitemap::sanitize_output($output)));	
		}
		else
		{
			return $template;
		}
	}

	public static function sanitize_output($buffer) {
		$search = array('/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s');
		$replace = array('>', '<', '\\1');
		$buffer = preg_replace($search, $replace, $buffer);
		return $buffer;
	}
	
	public static function polylang()
	{
		global $polylang;
		$output = false;
		
		if(isset($polylang))
		{
			$languages = PLL()->model->get_languages_list();
			$language_list = array();
			
			for($x = 0; $x < count($languages); $x++)
			{
				foreach($languages[$x] as $key => $value)
				{
					if($key == 'slug')
					{
						array_push($language_list, $value);
					}
				}	
			}
			if(count($language_list) > 0)
			{
				$output = $language_list;
			}
		}
		return $output;
	}
	
	public static function changefreq($post)
	{
		$output = 'weekly';
		
		if(isset($_GET['changefreq']))
		{
			$changefreq = sanitize_text_field($_GET['changefreq']);
			$changefreq_arr = array('always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never');
			
			if(in_array($changefreq, $changefreq_arr))
			{
				$output = $changefreq;
			}
			
		}
		else
		{
			if($post->ID == get_option('page_on_front') || $post->ID == get_option('page_for_posts') || minimal_sitemap::polylang_alt($post) == get_option('page_on_front') || minimal_sitemap::polylang_alt($post) == get_option('page_for_posts'))
			{
				$output = 'daily';
			}
			else if($post->post_type == 'post')
			{
				$output = 'monthly';
			}
		}
		return $output;
	}
	
	public static function polylang_alt($post)
	{
		$output = false;
		global $polylang;
		
		if(isset($polylang))
		{
			$output = pll_get_post($post->ID, pll_default_language());
		}
		return $output;
	}
	
}

?>