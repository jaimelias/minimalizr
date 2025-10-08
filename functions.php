<?php
/**
 * minimalizr functions and definitions
 *
 * @package minimalizr
 */
 

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Minimalizr {

	public function __construct()
	{
		$this->version = (is_array($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER) && $_SERVER['SERVER_NAME'] === 'localhost') ? time() : '1.5.6';
		$this->theme_name = 'minimalizr';
		$this->theme_directory = get_template_directory();
		add_action('init', array(&$this, 'init'));
		add_action( 'after_setup_theme', array(&$this, 'after_setup_theme') );
	

		add_action( 'admin_notices', array(&$this, 'dependencies'));
		add_filter('upload_mimes', array(&$this, 'add_svg_support'));
		add_filter( 'post_class', array(&$this, 'remove_hentry') );
		
		//meta tags
		add_filter('language_attributes', array(&$this, 'doctype_opengraph'));
		add_action( 'wp_head', array(&$this, 'meta_tags'));

		//page defaults
		add_filter('the_content', array(&$this, 'modify_content'));
		add_filter('the_title', array(&$this, 'modify_the_title'));
		add_filter('get_the_excerpt', array(&$this, 'modify_get_the_excerpt'));

		//scripts
		add_filter( 'script_loader_tag', array(&$this, 'async_defer_js'), 10, 3 );
		add_filter('minimal_ld_json', array(&$this, 'ld_json_cb'), 1, 3);
		add_action( 'wp_head', array(&$this, 'ld_json_script'));
		add_action( 'wp_enqueue_scripts', array(&$this, 'minimalizr_styles'), 0);
		add_action( 'wp_enqueue_scripts', array(&$this, 'minimalizr_scripts'), 0);
		add_action( 'enqueue_block_editor_assets', array(&$this, 'minimalizr_editor_styles'), 0);

		//handle 404 on tags an categories
		add_action('template_redirect', array(&$this, 'handle_404'));


		//shortcodes
		add_shortcode('obfuscate', array(&$this, 'hide_string'));
		add_shortcode('translate_string', array(&$this, 'translate_string'));

		//widgets
		add_filter( 'widget_text', 'do_shortcode');
		add_action( 'widgets_init', array(&$this, 'widgets_init'), 100 );

		//remove defaults
		remove_action('wp_head', 'feed_links', 2 );
		remove_action('wp_head', 'feed_links_extra', 3 ); 
		remove_action('wp_head', 'rest_output_link_wp_head');
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('template_redirect', 'rest_output_link_header', 11, 0 );
		remove_action ('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'wp_shortlink_wp_head');
		remove_action('wp_head', 'wp_generator');
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('admin_print_scripts', 'print_emoji_detection_script' );
		remove_action('admin_print_styles', 'print_emoji_styles' );
		add_filter('xmlrpc_enabled', '__return_false');

		//remove Beaver Builder (FLBuilder) defaults
		remove_action('wp_footer', 'FLBuilder::include_jquery');
	}

	public function init()
	{

		//require $this->theme_directory . '/dy-core/loader.php';

		require $this->theme_directory . '/inc/template-tags.php';

		require $this->theme_directory . '/inc/extras.php';

		require $this->theme_directory . '/inc/customizer.php';

		require $this->theme_directory . '/inc/bootstrap-nav-walker.php';

		require $this->theme_directory . '/inc/metaboxes.php';

		require $this->theme_directory . '/inc/minimal.php';

		require_once $this->theme_directory . '/inc/minimal-class.php';

		if(!defined('DY_CORE_FUNCTIONS'))
		{
			require_once $this->theme_directory . '/submodules/dy-core/loader.php';
		}

	}


	function get_inline_css($sheet)
	{
		ob_start();
		require_once($this->theme_directory . '/css/'.$sheet.'.css');
		$output = ob_get_contents();
		ob_end_clean();
		return $output;	
	}
	
	
	function async_defer_js($tag, $handle, $src)
	{
		if(preg_match("/async/i", $src) || preg_match("/defer/i", $src))
		{
			$method = '';
			
			if(preg_match("/async/i", $src))
			{
				$method .= ' async ';
			}
			if(preg_match("/defer/i", $src))
			{
				$method .= ' defer ';
			}
			
			$tag = '<script id="'.esc_attr($handle).'" type="text/javascript" '.esc_attr($method).' src="'.esc_url($src).'"></script>';
		}
		return $tag;
	}
	

	function translate_string($attr, $content = '')
	{
		$output = '';
		$languages = get_languages();
		$current_language = current_language();
		$text = '';
		$tag = 'div';
		$class_attr = '';
	
		for($x = 0; $x < count($languages); $x++)
		{
			$lang = $languages[$x];
	
			if($current_language === $lang)
			{
				if(array_key_exists($lang, $attr))
				{
					if(!empty($attr[$lang]))
					{
						$text = $attr[$lang];
					}
				}
	
				if(array_key_exists('tag', $attr))
				{
					if(!empty($attr['tag']))
					{
						$tag = $attr['tag'];
					}
				}
				
				if(array_key_exists('class', $attr))
				{
					if(!empty($attr['class']))
					{
						$class_attr = ' class="'.esc_attr($attr['class']).'" '; 
					}
				}
			}
		}
	
		if(!empty($text))
		{
			$output = '<'.esc_attr($tag).' '.$class_attr.'>'.esc_html($text).'</'.esc_attr($tag).'>';
		}
	
		return $output;
	}

	function modify_content($content)
	{
		if(is_category() || is_tag())
		{
			$content = get_the_archive_description();
		}
		return $content;
	}

	function modify_the_title($title)
	{
		if(is_tax() && in_the_loop())
		{
			$title = get_the_archive_title();
		}
		return $title;
	}

	function modify_get_the_excerpt($excerpt) {

		if(is_single() && is_main_query()) {

			global $post;

			if(empty($post->post_excerpt)) {
				 $excerpt = '';
			}
		}
		

		return $excerpt;
	}


	function hide_string($text, $content = '')
	{

		if(!is_array($text))
		{
			return '';
		}

		if(array_key_exists('email', $text))
		{
			$text = $text['email'];
			
			if(is_email($text))
			{
				$character_set = '+-.0123456789@ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz';
				$key = str_shuffle($character_set); $cipher_text = ''; $id = 'e'.rand(1,999999999);
				for ($i=0;$i<strlen($text);$i+=1) $cipher_text.= $key[strpos($character_set,$text[$i])];
				$script = 'var a="'.$key.'";var b=a.split("").sort().join("");var c="'.$cipher_text.'";var d="";';
				$script.= 'for(var e=0;e<c.length;e++)d+=b.charAt(a.indexOf(c.charAt(e)));';
				$script.= 'document.getElementById("'.$id.'").innerHTML=`<a href="mailto:${d}">${d}</a>`';
				$script = '<script>'.$script.'</script>';
				return '<span id="'.$id.'">[javascript protected email address]</span>'.$script;		
			}
		}
		if(array_key_exists('text', $text) && array_key_exists('url', $text))
		{
			$link = base64_encode('<a href="'.esc_url($text['url']).'" targe="_blank">'.esc_html($text['text']).'</a>');
			$script = '<script>document.write(atob("'.$link.'"));</script>';	
			return $script;
		}
	}

	function ld_json_cb($json = [])
	{
		$home_url = home_url();
		$contactPoint = [];
		$contactPoint['@type'] = 'ContactPoint';
		$contactPoint['contactType'] = 'customer service';
		$contactPoint['url'] = get_the_permalink();
		$contactPoint['sameAs'] = [];
		$media = array("facebook", "twitter", "linkedin", "youtube", "instagram", "pinterest", "google");
		
		for($x = 0; $x < count($media); $x++)
		{
				if(get_theme_mod($media[$x]) != null)
				{
					if(!filter_var(get_theme_mod($media[$x]), FILTER_VALIDATE_URL) === false)
					{
						array_push($contactPoint['sameAs'], get_theme_mod($media[$x]));
					}
				}	
		}		
		
		if(get_theme_mod('min_tel') != null)
		{
			$contactPoint['telephone'] = esc_html(get_theme_mod('min_tel'));
		}

		$organization = [
			'@context' => 'http://schema.org',
			'@type'    => 'Organization',
			'url'      => $home_url,
			'name'     => get_bloginfo('name'),
		];

		
		$site_icon = get_site_icon_url();

		if(!empty($site_icon))
		{
			$organization['logo'] = $site_icon;
		}
		if(get_theme_mod('min_address') != null)
		{
			$organization['address'] = get_theme_mod('min_address');
		}			
		
		$organization['contactPoint'] = $contactPoint;
		$json[] = $organization;
		
		if(is_singular('post'))
		{
			// --- begin modified block ---

			$mainEntityOfPage = [];
			$mainEntityOfPage['@type'] = 'WebPage';
			$mainEntityOfPage['@id'] = $home_url;
			$mainEntityOfPage['url'] = $home_url;
			
			$author = [];
			$author['@type'] = 'Person';
			
			while ( have_posts() )
			{
				the_post(); 
				global $post;
				$author['name'] = get_the_author();
			}
			wp_reset_query(); 
			
			$publisher = [];
			
			if(!empty($site_icon))
			{
				$logo = [];
				$logo['@type'] = 'ImageObject';
				$logo['url'] = $site_icon;
				$publisher['@type'] = 'Organization';
				$publisher['name'] = esc_html(get_bloginfo('name'));
				$publisher['logo'] = $logo;			
			}
			
			$image = [];
			if(has_post_thumbnail())
			{
				$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'full');
				$image['@type'] = 'ImageObject';
				$image['url'] = esc_url(get_the_post_thumbnail_url());		
				$image['width'] = esc_html($image_data[1]);		
				$image['height'] = esc_html($image_data[2]);		
			}

			// Extra helpful fields for an Article
			$post_id        = get_the_ID();
			$post_lang      = function_exists('get_locale') ? get_locale() : '';
			$post_content   = wp_strip_all_tags( get_post_field('post_content', $post_id) );
			$post_wordcount = str_word_count( $post_content );

			// Category -> articleSection (first category name if available)
			$cats = get_the_category($post_id);
			$article_section = (!empty($cats) && isset($cats[0]->name)) ? $cats[0]->name : null;

			// Tags -> keywords (comma-separated)
			$tags = wp_get_post_tags($post_id, ['fields' => 'names']);
			$keywords = !empty($tags) ? implode(', ', array_map('esc_html', $tags)) : null;

			$article = [
				'@context'         => 'http://schema.org',
				'@type'            => 'Article',
				'headline'         => esc_html(get_the_title()),
				'url'              => esc_url(get_the_permalink()),
				'datePublished'    => esc_html(get_the_date('c')),
				'dateModified'     => esc_html(get_the_modified_date('c')),
				'mainEntityOfPage' => $mainEntityOfPage,
				'author'           => $author,
				'inLanguage'       => $post_lang ?: null,
				'articleBody'      => mb_substr( $post_content, 0, 5000 ), // keep payload sane
				'wordCount'        => $post_wordcount,
				'description'      => esc_html(get_the_excerpt()),
			];

			if($article_section){
				$article['articleSection'] = esc_html($article_section);
			}
			if($keywords){
				$article['keywords'] = $keywords;
			}
			
			if(array_key_exists('name', $publisher))
			{
				$article['publisher'] = $publisher;
			}
			
			if(has_post_thumbnail())	
			{
				$article['image'] = $image;
			}

			$json[] = $article;

			// --- end modified block ---
		}

		return $json;
	}



	function ld_json_script() {
		$ld = apply_filters('minimal_ld_json', []);

		if (empty($ld) || !is_array($ld)) {
			return;
		}

		$scripts = ["\n"];
		foreach (array_values($ld) as $i => $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$scripts[] = sprintf(
				'<script type="application/ld+json" id="json_ld_%d">%s</script>',
				$i + 1,
				wp_json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			);
		}

		if ($scripts) {
			echo implode("\n", $scripts);
		}
	}





	function doctype_opengraph($output) {
		$output = $output ."\r\n\t".'prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb#"';
		return $output;
	}
	
	
	function meta_tags() {
	
		global $wp;
		global $post;
		$description = null;	
		$url =  get_permalink();
		
		
		if(isset($post))
		{
			$title = $post->post_title;
			
			if(has_excerpt())
			{
				$description = (strlen($post->post_excerpt) < 50) ? $title.'. '.$post->post_excerpt : $post->post_excerpt;
			}
			
			$description = apply_filters('minimal_description', $description);
		}
		if(is_tax())
		{
			$tax = get_taxonomy( get_queried_object()->taxonomy );
			$title = $tax->labels->singular_name.': '. single_term_title( '', false );
			$url = home_url(add_query_arg(array(),$wp->request));
			
			$Parsedown = new Parsedown();
			$description = get_term(get_queried_object()->term_id)->description;
			$description = strip_shortcodes($description);
			$description = $Parsedown->line($description);
			$description = strip_tags($description);
		}
		
		$description = $this->limit_200($description);	
			
		$image = wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) );
		
		
		?>
			<?php if(is_singular() || is_tax()): ?>
	
				<meta property="og:site_name" content="<?php bloginfo('name');?>" />
				<meta property="og:type" content="website" />
				<meta property="og:title" content="<?php echo esc_html($title); ?>" />
				<meta property="og:url" content="<?php echo esc_url($url); ?>" />
				
				<?php if($description != null): ?>
					<meta name="description" content="<?php esc_html_e($description);?>" />
					<meta property="og:description" content="<?php esc_html_e($description);?>" />
				<?php endif; ?>
	
				<?php if(has_post_thumbnail() && !is_tax()): ?>
					<meta property="og:image" content="<?php echo esc_url($image);?>" />
					<meta itemprop="image" content="<?php echo esc_url($image);?>" />
				<?php endif; ?>
			<?php endif; ?>
	
		<?php
	}
	

	function remove_hentry( $classes ) {

		if ( !is_single() ) {
			$classes = array_diff( $classes, array( 'hentry' ) );
		}
		if(is_singular('post'))
		{
			$classes[] = 'bottom-40';
		}
		if(is_home())
		{
			$classes[] = 'bottom-40';
		}
		return $classes;
	}
	

	function add_svg_support($mimes) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}
	

	function dependencies() {
	
		//you can add plugin dependency easy by filling this 3 arrays
		$check_function = array("FLBuilder");
		$functionName = array("Beaver Builder - WordPress Page Builder");
		$functionUrl = array("plugin-install.php?tab=plugin-information&plugin=beaver-builder-lite-version");
		
		$count = 0;
		$output = '';
		
		for($x = 0; $x < count($check_function); $x++)
		{
			if(!class_exists($check_function[$x]))
			{
				$count++;
			}
		}
		
		if($count > 0)
		{
			$output .= '<div class="error"><p><strong>'.__('Warning', 'minimalizr').':</strong> '.__( 'The following plugins are required by the current theme:', 'minimalizr' ).'</p><ul>';
				for($i = 0; $i < count($check_function); $i++)
				{
					if(!class_exists($check_function[$i]))
					{
						$output .= '<li><a href="'.esc_url(admin_url($functionUrl[$i])).'" >'.$functionName[$i].'</a></li>';
					}
				}		
			$output .= '</ul></div>';
		}
		echo $output;
	
	}
	

	function minimalizr_scripts() {
	
		global $wp_scripts;
		
		if(is_admin()) return;
		
		$theme_url = get_template_directory_uri();
		
		if(!isset($_GET['fl_builder']) && !is_user_logged_in())
		{
			wp_deregister_script('jquery');
			wp_register_script('jquery', $theme_url.'/js/jquery-3.6.1.slim.min.js', false, null, true);
			wp_enqueue_script('jquery');
		}

		//temp jquery slim fixes and $ fixes
		wp_add_inline_script('jquery', $this->js_temp_fixes(), 'after');

		wp_enqueue_script( 'minimalizr-sidebar-menuJS', esc_url($theme_url . '/js/sidebar-menu.js?async_defer=true'), array('jquery'), $this->version, true );
		
		$disqus = get_theme_mod('disqus');

		if (!empty($disqus)) {
			
			if(is_singular('post'))
			{
				wp_add_inline_script('jquery', $this->disqus_script());
			}
			else if(is_home())
			{
				wp_enqueue_script( 'dsq-count-scr', 'https://'.$disqus.'.disqus.com/count.js', '', 'async_defer', true);			
			}
		}		
	}
	

	function minimalizr_editor_styles()
	{
		if(is_admin())
		{
			$theme_url = get_template_directory_uri();
			wp_enqueue_style( 'minimalLayout', $theme_url.'/css/minimal-layout.css', array(), $this->version);
		}
	}

	function minimalizr_styles(){
	
		if(is_admin()){
			return '';
		};

		$theme_url = get_template_directory_uri();
	
		wp_enqueue_style( 'minimalLayout', $theme_url.'/css/minimal-layout.css', array(), $this->version);
		wp_enqueue_style( 'minimalizr-style', get_stylesheet_uri(), array( 'minimalLayout'), $this->version);		
		wp_add_inline_style( 'minimalizr-style', $this->get_inline_css('media-query'), $this->version);
		wp_enqueue_style( 'dashicons' );
	}
	
	
	function widgets_init() {
		$sidebarLabel = array(
			__( 'Footer 1', 'minimalizr' ),
			__( 'Footer 2', 'minimalizr' ),
			__( 'Footer 3', 'minimalizr' ),
			__( 'Footer 4', 'minimalizr' ),
			__( 'Page', 'minimalizr' ),
			__( 'Blog', 'minimalizr' ));
	
		for($x = 0; $x < count($sidebarLabel); $x++)
		{
			register_sidebar( array(
				'name'          => esc_html($sidebarLabel[$x]),
				'id'            => esc_attr('sidebar-' . ($x+1)),
				'description'   => '',
				'before_widget' => '<aside id="%1$s" class="widget %2$s">',
				'after_widget'  => '</aside>',
				'before_title'  => '<div class="widget-title">',
				'after_title'   => '</div>',
			));	
		}
	}
	

	function get_custom_headers()
	{
		return array(
			'width' => 100,
			'height' => 40,
			'flex-height' => true,
			'flex-width' => true,
			'uploads' => true,
			'random-default' => false,
			'header-text' => false,
			'wp-head-callback' => '',
			'admin-head-callback' => '',
			'admin-preview-callback' => '',
		);
	}

	function after_setup_theme() {

		load_theme_textdomain( 'minimalizr', $this->theme_directory . '/languages' );
		add_theme_support( 'title-tag' );
		register_nav_menus(array('primary' => 'Primary Menu'));
		add_theme_support( 'custom-header', $this->get_custom_headers());
		add_theme_support('post-thumbnails');
		add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
		add_theme_support( 'custom-background', apply_filters( 'minimalizr_custom_background_args', array('default-color' => 'ffffff', 'default-image' => '') ) );
		add_post_type_support( 'page', 'excerpt' );
		$GLOBALS['content_width'] = apply_filters( 'minimalizr_content_width', 640 );
	}

	public function js_temp_fixes()
	{
		ob_start();
		require_once($this->theme_directory . '/js/temp-fixes.js');
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	function disqus_script()
	{
		ob_start();
		?>
		var disqus_config = function () {
			this.page.url = '<?php the_permalink(); ?>'; 
			this.page.identifier = '<?php echo get_the_ID(); ?>';
		};
	
		(function() {
			var d = document, s = d.createElement('script');
			s.src = 'https://<?php echo esc_html(get_theme_mod('disqus')); ?>.disqus.com/embed.js';
			s.setAttribute('data-timestamp', +new Date());
			(d.head || d.body).appendChild(s);
		})();
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	function limit_200($x)
	{
		if(!empty($x))
		{
			$length = 200;
			$replace = array("\r\n", "\n", "\r", "#");
			$x = str_replace($replace, ' ', $x);
			$x = preg_replace('/\s+/', ' ', $x);
			$x = trim($x);
			$x = mb_substr($x, 0, $length);
		}
	
		return $x;
	}

	function handle_404() {
		// Only act on 404 pages
		if ( ! is_404() ) {
			return;
		}

		// Detect if the request is for a non-existent tag/category
		$is_term_request =
			get_query_var('tag') ||
			get_query_var('category_name') ||
			get_query_var('cat') ||
			( get_query_var('taxonomy') === 'category' ) ||
			( get_query_var('taxonomy') === 'post_tag' );

		if ( ! $is_term_request ) {
			return;
		}

		// Get posts page or fallback to home
		$blog_page_id = (int) get_option('page_for_posts');

		if($blog_page_id === 0) return;

		$target_id    = $blog_page_id > 0 ? $blog_page_id : 0;

		global $polylang;

		// Polylang-aware redirect
		if ( isset( $polylang ) ) {
			$lang = current_language();
			if ( $lang ) {
				$translated = pll_get_post($blog_page_id, $lang);
				if ( $translated ) {
					$target_id = (int) $translated;
				}
			}
		}

		// Build final URL
		$redirect_url = $target_id ? get_permalink($target_id) : home_url('/');

		if ( ! $redirect_url || headers_sent() ) {
			return;
		}

		// Permanent redirect
		wp_safe_redirect($redirect_url, 301);
		exit;
	}

}

new Minimalizr();



