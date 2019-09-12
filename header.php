<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link href="<?php echo esc_url(home_url()); ?>" rel="home" />
<?php do_action('minimal_menu_css'); ?>
<?php wp_head(); ?>
</head>


<body <?php body_class(); ?>>

<?php if(get_theme_mod('tagmanager_container_id') != null): ?>	
	<!-- Google Tag Manager (noscript) -->
	<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_html(get_theme_mod('tagmanager_container_id')); ?>"
	height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	<!-- End Google Tag Manager (noscript) -->
<?php endif; ?>

<div id="wrapper" class="clearfix">	
	
<?php do_action('minimal_menu'); ?>
			


<div id="page-content-wrapper" class="clearfix custom-background">

<?php 

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
