<?php
/**
 * minimalizr functions and definitions
 *
 * @package minimalizr
 */
 

if ( ! function_exists( 'minimalizr_setup' ) ) :

function minimalizr_setup() {


	load_theme_textdomain( 'minimalizr', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'title-tag' );

	register_nav_menus( array(
		'primary' => esc_html__( 'Primary Menu', 'minimalizr' ),
	) );
		
	$customheader = array(
	'width'                  => 100,
	'height'                 => 40,
	'flex-height'            => true,
	'flex-width'             => true,
	'uploads'                => true,
	'random-default'         => false,
	'header-text'            => false,
	'wp-head-callback'       => '',
	'admin-head-callback'    => '',
	'admin-preview-callback' => '',
	);
	add_theme_support( 'custom-header', $customheader );

	
	add_theme_support( 'post-thumbnails');
	
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
	) );

	add_theme_support( 'post-formats', array(
		'aside', 'image', 'video', 'quote', 'link',
	) );

	add_theme_support( 'custom-background', apply_filters( 'minimalizr_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );

	add_post_type_support( 'page', 'excerpt' );	
}
endif; // minimalizr_setup
add_action( 'after_setup_theme', 'minimalizr_setup' );


/**
 * Set the setup to the admin 
 *
 * 
 *
 * @global int $content_width
 */
function admin_setup_theme()
{
	add_editor_style( 'css/editor.css' );	
}
add_action( 'admin_init', 'admin_setup_theme' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function minimalizr_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'minimalizr_content_width', 640 );
}
add_action( 'after_setup_theme', 'minimalizr_content_width', 0 );

/**
 * Register widget area.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_sidebar
 */
function minimalizr_widgets_init() {
	$sidebarId = array(
		"sidebar-1",
		"sidebar-2",
		"sidebar-3",
		"sidebar-4",
		"sidebar-5",
		"sidebar-6");
	$sidebarLabel = array(
		esc_html__( 'Footer 1', 'minimalizr' ),
		esc_html__( 'Footer 2', 'minimalizr' ),
		esc_html__( 'Footer 3', 'minimalizr' ),
		esc_html__( 'Footer 4', 'minimalizr' ),
		esc_html__( 'Page', 'minimalizr' ),
		esc_html__( 'Blog', 'minimalizr' ));

	for($x = 0; $x < count($sidebarId); $x++)
	{
		register_sidebar( array(
			'name'          => $sidebarLabel[$x],
			'id'            => $sidebarId[$x],
			'description'   => '',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<div class="widget-title">',
			'after_title'   => '</div>',
		));	
	}
}
add_action( 'widgets_init', 'minimalizr_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
 
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

function minimalizr_styles(){
	
	if(is_admin()) return;
	$theme_url = get_template_directory_uri();
	
	wp_dequeue_style('contact-form-7');
	wp_enqueue_style( 'minimalLayout', esc_url($theme_url.'/css/minimal-layout.css'), array());
	wp_enqueue_style( 'minimalizr-style', esc_url(get_stylesheet_uri()), array( 'minimalLayout'), time());		
    wp_add_inline_style( 'minimalizr-style', get_inline_css('media-query'));	
}

add_action( 'wp_enqueue_scripts', 'minimalizr_styles', 0);

function minimalizr_scripts() {
	
	global $wp_scripts;
	
	if(is_admin()) return;
	
	$theme_url = get_template_directory_uri();
	$wp_scripts->registered['jquery-core']->src = 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js';
    $wp_scripts->registered['jquery']->deps = ['jquery-core'];	
	
	wp_enqueue_script( 'minimalizr-sidebar-menuJS', esc_url($theme_url . '/js/sidebar-menu.js'), array('jquery'), '', true );	
	wp_enqueue_script( 'landing-cookies', esc_url($theme_url . '/js/cookies.js'), array('jquery'), '', true);
	
	if (get_theme_mod('disqus') != null) {
		
		if(is_singular('post'))
		{
			wp_add_inline_script('jquery', disqus_script());
		}
		else if(is_home())
		{
			$count_link = 'https://'.get_theme_mod('disqus').'.disqus.com/count.js';
			wp_enqueue_script( 'dsq-count-scr', esc_url($count_link), '', 'async_defer', true);			
		}
	}	
	
	wp_enqueue_script( 'minimal-fontawesome', 'https://use.fontawesome.com/releases/v5.3.1/js/all.js?async=async', '', '', true);
}
add_action( 'wp_enqueue_scripts', 'minimalizr_scripts', 0);

function cf7_dequeue_recaptcha()
{
	$dequeu = true;
	
	if(is_singular())
	{
		global $post;
		
		if(has_shortcode($post->post_content, 'contact-form-7'))
		{
			$dequeu = false;
		}
	}
	
	if(is_tax())
	{
		$tax = get_taxonomy( get_queried_object()->taxonomy );
		$description = get_term(get_queried_object()->term_id)->description;
		
		if(has_shortcode($description, 'contact-form-7'))
		{
			$dequeu = false;
		}
	}
	
	if($dequeu === true)
	{
		wp_dequeue_script('google-recaptcha');
	}
	else
	{
		wp_enqueue_script('google-recaptcha');
	}
}

add_action( 'wp_enqueue_scripts', 'cf7_dequeue_recaptcha', 11);



// Check if Site Origin is installed

function my_theme_dependencies() {
	
	//you can add plugin dependency easy by filling this 3 arrays
	$check_function = array("FLBuilder");
	$functionName = array("Beaver Builder - WordPress Page Builder");
	$functionUrl = array("plugin-install.php?tab=plugin-information&plugin=beaver-builder-lite-version");
	
	$count = 0;
	$output = "";
	
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
add_action( 'admin_notices', 'my_theme_dependencies' );




//add svg upload support
function cc_mime_types($mimes) {
  $mimes['svg'] = 'image/svg+xml';
  return $mimes;
}
add_filter('upload_mimes', 'cc_mime_types');


function minimalizr_header()
{
	if(substr(get_custom_header()->url, -3) == 'svg')
	{
		$svg = simplexml_load_file(get_attached_file(get_custom_header()->attachment_id)); 
		$svg_attr = $svg->attributes();
		$svg_sizes = explode(' ', $svg_attr->viewBox);
		$header_width = $svg_sizes[2];
		$header_height = $svg_sizes[3];
	}
	else
	{
		$header_width = get_custom_header()->width;
		$header_height =get_custom_header()->height;
	}
	$header['width'] = round($header_width);
	$header['height'] = round($header_height);
	return (object) wp_parse_args($header);
}


function themeslug_remove_hentry( $classes ) {
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
add_filter( 'post_class','themeslug_remove_hentry' );


/**
 * Home Lang Url
 */
function home_lang()
{
	global $polylang;
	if($polylang)
	{
		return pll_home_url();
	}
	else
	{
		return home_url('/');
	}
}

add_action('wp_head', 'ogp_alternate');
function ogp_alternate() {
	global $polylang;
	
	if($polylang)
	{
		foreach ($polylang->model->get_languages_list() as $language) {
			if ($language->slug != $polylang->curlang->slug && $url = $polylang->links->get_translation_url($language))
				printf('<meta property="og:locale:alternate" content="%s" />'."\n", esc_attr($language->locale));
			if ($language->slug == $polylang->curlang->slug && $url = $polylang->links->get_translation_url($language))
				printf('<meta property="og:locale" content="%s" />'."\n", esc_attr($language->locale));
		}		
	}
}

function limit_350($x)
{
	$length = 350;
	$replace = array("\r\n", "\n", "\r", "#");
	$x = str_replace($replace, " ", $x);
	$x = preg_replace('/\s+/', ' ', $x);
	$x = ltrim($x, ' ');
	return substr($x, 0, $length);
}

function minimalizr_render_meta_tags() {
	
	global $wp;
	$description = null;
	$title = get_the_title();
	$url =  get_permalink();
	
	
	if(has_excerpt())
	{
		$description = get_the_title().'. '.get_the_excerpt();
	}
	if(is_tax())
	{
		$tax = get_taxonomy( get_queried_object()->taxonomy );
		$title = esc_html($tax->labels->singular_name).': '.esc_html(single_term_title( '', false ));
		$url = home_url(add_query_arg(array(),$wp->request));
		
		$Parsedown = new Parsedown();
		$description = get_term(get_queried_object()->term_id)->description;
		$description = strip_shortcodes($description);
		$description = $Parsedown->line($description);
		$description = strip_tags($description);
	}
	
	$description = limit_350($description);	
		
	$image = wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) );
	
	
	?>
		<?php if(is_singular() || is_tax()): ?>

			<meta property="og:site_name" content="<?php bloginfo('name');?>" />
			<meta property="og:type" content="website" />
			<meta property="og:title" content="<?php echo esc_html($title); ?>" />
			<meta property="og:url" content="<?php echo esc_url($url); ?>" />
			
			<?php if($description != null): ?>
				<meta name="description" content="<?php echo esc_html($description);?>" />
				<meta property="og:description" content="<?php echo esc_html($description);?>" />
			<?php endif; ?>

			<?php if(has_post_thumbnail() && !is_tax()): ?>
				<meta property="og:image" content="<?php echo esc_url($image);?>" />
				<meta itemprop="image" content="<?php echo esc_url($image);?>" />
			<?php endif; ?>
		<?php endif; ?>

	<?php
}
add_action( 'wp_head', 'minimalizr_render_meta_tags');

function add_analytics_tracking_code()
{
	if(get_theme_mod('analytics_tracking_id') != null)
	{
		ob_start();
		require_once(get_template_directory() . '/js/analytics_tracking_code.php');
		$content = ob_get_contents();
		ob_end_clean();	
		echo $content;		
	}
}

add_action( 'wp_head', 'add_analytics_tracking_code', 50);


function add_favicon()
{
	if(get_theme_mod('minimalizr_fav_icon'))
	{
		echo '<link rel="shortcut icon" href="'.esc_url(get_theme_mod('minimalizr_fav_icon')).'" />';
	}
}
add_action( 'wp_head', 'add_favicon');


function ld_json_cb($json)
{
	
	if(is_front_page())
	{
		
		$contactPoint = array();
		$contactPoint['@type'] = 'ContactPoint';
		$contactPoint['contactType'] = 'customer service';
		$contactPoint['url'] = esc_url(get_the_permalink());
		$contactPoint['sameAs'] = array();
		
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
		
		$json = array();
		$json['@context'] = 'http://schema.org';
		$json['@type'] = 'Organization';
		$json['url'] = esc_url(home_url());
		$json['name'] = esc_html(get_bloginfo('name'));
		
		if(get_theme_mod('minimalizr_large_icon'))
		{
			$json['logo'] = get_theme_mod('minimalizr_large_icon');
		}
		if(get_theme_mod('min_address') != null)
		{
			$json['address'] = get_theme_mod('min_address');
		}			
		
		$json['contactPoint'] = $contactPoint;
	}
	elseif(is_singular('post'))
	{
		$mainEntityOfPage = array();
		$mainEntityOfPage['@type'] = 'WebPage';
		$mainEntityOfPage['@id'] = esc_url(get_the_permalink());
		$mainEntityOfPage['url'] = esc_url(get_the_permalink());
		
		$author = array();
		$author['@type'] = 'Person';
		
		while ( have_posts() )
		{
			the_post(); 
			global $post;
			$author['name'] = get_the_author();
		}
		wp_reset_query(); 
		
		$publisher = array();
		
		if(get_theme_mod('minimalizr_large_icon'))
		{
			$logo = array();
			$logo['@type'] = 'ImageObject';
			$logo['url'] = esc_url(get_theme_mod('minimalizr_large_icon'));
			$json['logo'] = $logo;
			$publisher['@type'] = 'Organization';
			$publisher['name'] = esc_html(get_bloginfo('name'));
			$publisher['logo'] = $logo;			
		}
		
		$image = array();
		if(has_post_thumbnail())
		{
			$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'full');
			$image['@type'] = 'ImageObject';
			$image['url'] = esc_url(get_the_post_thumbnail_url());		
			$image['width'] = esc_html($image_data[1]);		
			$image['height'] = esc_html($image_data[2]);		
		}

		
		$json = array();
		$json['@context'] = 'http://schema.org';
		$json['@type'] = 'Article';
		$json['headline'] = esc_html(get_the_title());
		$json['datePublished'] = esc_html(get_the_date('c'));
		$json['dateModified'] = esc_html(get_the_modified_date('c'));
		$json['mainEntityOfPage'] = $mainEntityOfPage;
		$json['author'] = $author;
		
		if(array_key_exists('name', $publisher))
		{
			$json['publisher'] = $publisher;
		}
		
		$json['description'] = esc_html(get_the_excerpt());
		
		if(has_post_thumbnail())	
		{
			$json['image'] = $image;
		}		
	}
	return $json;
}

add_filter('minimal_ld_json', 'ld_json_cb', 1, 3);


function ld_json_script()
{
	echo '<script type="application/ld+json">'.json_encode(apply_filters('minimal_ld_json', array())).'</script>';
}

add_action( 'wp_head', 'ld_json_script');


function add_google_plus_rel()
{
	if(get_theme_mod('google-plus') != null)
	{
		echo '<link href="'.esc_url(get_theme_mod('google-plus')).'" rel="publisher" />';
	}
}
add_action( 'wp_head', 'add_google_plus_rel');


function doctype_opengraph($output) {
	$output = $output ."\r\n\t".'prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb#"';
    return $output;
}
add_filter('language_attributes', 'doctype_opengraph');

remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 ); 
remove_action( 'wp_head', 'rest_output_link_wp_head');
remove_action( 'wp_head', 'wp_oembed_add_discovery_links');
remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
remove_action ('wp_head', 'rsd_link');
remove_action( 'wp_head', 'wlwmanifest_link');
remove_action( 'wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );


// First, make sure Jetpack doesn't concatenate all its CSS
add_filter( 'jetpack_implode_frontend_css', '__return_false' );

// disable xml-rpc xmlrp
add_filter('xmlrpc_enabled', '__return_false');


function remove_jetpack_styles() {
  wp_deregister_style( 'AtD_style' ); // After the Deadline
  wp_deregister_style( 'jetpack_likes' ); // Likes
  wp_deregister_style( 'jetpack_related-posts' ); //Related Posts
  wp_deregister_style( 'jetpack-carousel' ); // Carousel
  wp_deregister_style( 'grunion.css' ); // Grunion contact form
  wp_deregister_style( 'the-neverending-homepage' ); // Infinite Scroll
  wp_deregister_style( 'infinity-twentyten' ); // Infinite Scroll - Twentyten Theme
  wp_deregister_style( 'infinity-twentyeleven' ); // Infinite Scroll - Twentyeleven Theme
  wp_deregister_style( 'infinity-twentytwelve' ); // Infinite Scroll - Twentytwelve Theme
  wp_deregister_style( 'noticons' ); // Notes
  wp_deregister_style( 'post-by-email' ); // Post by Email
  wp_deregister_style( 'publicize' ); // Publicize
  wp_deregister_style( 'sharedaddy' ); // Sharedaddy
  wp_deregister_style( 'sharing' ); // Sharedaddy Sharing
  wp_deregister_style( 'stats_reports_css' ); // Stats
  wp_deregister_style( 'jetpack-widgets' ); // Widgets
  wp_deregister_style( 'jetpack-slideshow' ); // Slideshows
  wp_deregister_style( 'presentations' ); // Presentation shortcode
  wp_deregister_style( 'jetpack-subscriptions' ); // Subscriptions
  wp_deregister_style( 'tiled-gallery' ); // Tiled Galleries
  wp_deregister_style( 'widget-conditions' ); // Widget Visibility
  wp_deregister_style( 'jetpack_display_posts_widget' ); // Display Posts Widget
  wp_deregister_style( 'gravatar-profile-widget' ); // Gravatar Widget
  wp_deregister_style( 'widget-grid-and-list' ); // Top Posts widget
  wp_deregister_style( 'jetpack-widgets' ); // Widgets
}
add_action('wp_print_styles', 'remove_jetpack_styles' );

function messenger_button()
{
	if(get_theme_mod('messenger') != null)
	{
		$output = '<a title="Facebook Messenger" class="button-messenger pure-button" target="_blank" href="'.esc_url('https://m.me/'.get_theme_mod('messenger')).'" ><i class="fab fa-facebook-messenger"></i> '.esc_html(__('Messenger', 'minimalizr')).'</a>';		
	}
	else
	{
		$output = '<p class="small">'.esc_html(__('Facebook page name is not set yet.', 'minimalizr')).'</p>';
	}
	return $output;
}
add_shortcode( 'messenger', 'messenger_button' );

function skype_button()
{
	if(get_theme_mod('skype') != '')
	{
		$output = '<a title="Skype Call" class="button-skype pure-button" target="_blank" href="'.('skype:'.get_theme_mod('skype')).'" ><i class="fab fa-skype"></i> '.esc_html(__('Skype', 'minimalizr')).'</a>';		
	}
	else
	{
		$output = '<p class="small">'.esc_html(__('Skype username is not set yet.', 'minimalizr')).'</p>';
	}
	return $output;
}
add_shortcode( 'skype', 'skype_button' );


function whatsapp_button($label = '', $text = '')
{
	$output = null;
	
	if(get_theme_mod('whatsapp') != null)
	{
		$number = preg_replace('/[^0-9.]+/', '', get_theme_mod('whatsapp'));
		
		if($label == '')
		{
			$label = 'Whatsapp';
		}
		
		if($text == '')
		{
			if(is_singular())
			{
				global $post;
				$text = $post->post_title;
			}
			else if(is_tax())
			{
				$text = single_term_title( '', false);
			}
			else{
				$text = get_bloginfo('name');
			}	
		}
		
		
		$text =  '?text='.urlencode($text);
		
		$url = 'https://wa.me/'.$number.$text;
		$output = '<a class="pure-button button-whatsapp" target="_blank" href="'.esc_url($url).'"><i class="fab fa-whatsapp"></i> '.esc_html($label).'</a>';
	}
	return $output;
}
add_shortcode( 'whatsapp', 'whatsapp_button' );

function sales_phone($class = null)
{
	if(get_theme_mod('sales_phone') != null)
	{
		if($class != null)
		{
			$class = 'class="'.esc_html($class).'"';
		}
		return '<span '.esc_html($class).'><i class="fas fa-phone" ></i> '.esc_html(get_theme_mod('sales_phone')).'</span>';		
	}
}

function modify_content($content)
{
	if(is_tax())
	{
		$content = get_the_archive_description();
	}
	return $content;
}
add_filter('the_content', 'modify_content');

function modify_the_title($title)
{
	if(is_tax() && in_the_loop())
	{
		$title = get_the_archive_title();
	}
	return $title;
}
add_filter('the_title', 'modify_the_title');

function hide_sting($text, $content = '')
{

	if(is_array($text))
	{
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
				$script.= 'document.getElementById("'.$id.'").innerHTML="<a href=\\"mailto:"+d+"\\">"+d+"</a>"';
				$script = "eval(\"".str_replace(array("\\",'"'),array("\\\\",'\"'), $script)."\")"; 
				$script = '<script type="text/javascript">/*<![CDATA[*/'.$script.'/*]]>*/</script>';
				return '<span id="'.$id.'">[javascript protected email address]</span>'.$script;		
			}
		}
		if(array_key_exists('text', $text) && array_key_exists('url', $text))
		{
			$link = base64_encode('<a href="'.esc_url($text['url']).'" targe="_blank">'.esc_html($text['text']).'</a>');
			$script = '<script type="text/javascript">document.write(atob("'.$link.'"));</script>';	
			return $script;
		}
	}
}

add_shortcode('obfuscate', 'hide_sting');

if ( ! function_exists('write_log')) {
	function write_log ( $log )  {
		
		if ( is_array( $log ) || is_object( $log ) ) {

			$log .= ' '.sanitize_text_field($_SERVER['REQUEST_URI']);  
			$log .= ' '.sanitize_text_field($_SERVER['HTTP_USER_AGENT']);  
			error_log( print_r( $log, true ) );
		}
		else
		{
			$log .= ' '.sanitize_text_field($_SERVER['REQUEST_URI']);  
			$log .= ' '.sanitize_text_field($_SERVER['HTTP_USER_AGENT']);  
			error_log( $log );
		}
	}
}

function async_defer_JS($tag, $handle, $src)
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
		
		$tag = '<script id="'.esc_html($handle).'" type="text/javascript" '.esc_html($method).' src="'.esc_url($src).'"></script>';
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'async_defer_JS', 10, 3 );


/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require get_template_directory() . '/inc/extras.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require get_template_directory() . '/inc/jetpack.php';

require get_template_directory() . '/inc/bootstrap-nav-walker.php';

require get_template_directory() . '/inc/metaboxes.php';

require get_template_directory() . '/inc/minimal.php';

//sitemap
require_once get_template_directory() . '/inc/minimal_sitemap/sitemap.php';	


function get_inline_css($sheet)
{
	ob_start();
	require_once(get_template_directory() . '/css/'.$sheet.'.css');
	$output = ob_get_contents();
	ob_end_clean();
	return $output;	
}

function hide_admin_bar(){
	if(!is_user_logged_in())
	{
		if(!isset($_GET['fl_builder']))
		{
			return false;
		}
	}
}
//add_filter( 'show_admin_bar' , 'hide_admin_bar');

remove_action('wp_footer', 'FLBuilder::include_jquery');

add_filter( 'widget_text', 'do_shortcode' );

function facebook_pixel()
{
	if(get_theme_mod('facebook_pixel_id') != null)
	{
		ob_start();
		?>
			<script>
			!function(f,b,e,v,n,t,s)
			{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
			n.callMethod.apply(n,arguments):n.queue.push(arguments)};
			if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
			n.queue=[];t=b.createElement(e);t.async=!0;
			t.src=v;s=b.getElementsByTagName(e)[0];
			s.parentNode.insertBefore(t,s)}(window,document,'script',
			'https://connect.facebook.net/en_US/fbevents.js');
			 fbq('init', '<?php echo esc_html(get_theme_mod('facebook_pixel_id')); ?>'); 
			fbq('track', 'PageView');
			</script>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		echo $output;
	}
}
add_action('wp_head', 'facebook_pixel');

require_once get_template_directory() . '/inc/minimal-class.php';

