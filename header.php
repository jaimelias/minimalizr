<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link rel="profile" href="http://gmpg.org/xfn/11">
<link href="<?php echo esc_url(home_url()); ?>" rel="home" />
<?php do_action('minimal_menu_css'); ?>
<?php wp_head(); ?>
</head>


<body <?php body_class(); ?>>

<?php do_action('minimal_pre_body'); ?>

<div id="minimal-wrapper" class="clearfix">	
	
<?php do_action('minimal_menu'); ?>

<div id="page-content-wrapper" class="clearfix custom-background">

<?php 

	$is_tax = is_tax();
	$title = ($is_tax) ? html_entity_decode(get_the_title()) : get_the_title();
	$title = (is_front_page()) ?  '<h2 class="entry-title">'.$title.'</h2>' : '<h1 class="entry-title">'.$title.'</h1>';
	$description = '';

	if(is_singular() && has_excerpt())
	{
		$excerpt = get_the_excerpt();

		if(!empty($excerpt))
		{
			$description = '<p itemprop="description" class="large bottom-10">'.$excerpt.'</p>';

			if(!in_array('bodyfull', get_body_class()))
			{
				$description .= '<hr/>';
			}
		}
	}

	if($is_tax)
	{
		if(!empty(term_description()))
		{
			$description = '<p itemprop="description" class="large bottom-10">'.esc_html(get_term(get_queried_object()->term_id)->description).'</p>';
		}
	}


	if(in_array('bodyfull', get_body_class())) {
		echo '<div class="minimal-box text-center"><div class="container">'.$title.$description.'</div></div>';
	}

	if(minimalizr_get_meta( "minimalizr_width" ) === "full" && (is_page() || is_single()))
	{
		$layoutwidth = 'layoutfull';
		$max_width = '100%';
	}
	else
	{
		$layoutwidth = 'layoutfixed';
		$max_width = '1000px';
	}

?>

<div id="content" class="site-content clearfix <?php echo $layoutwidth; ?>" style="max-width: <?php echo $max_width; ?>" >
