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

<?php wp_body_open(); ?>

<?php do_action('minimal_pre_body'); ?>

<div id="minimal-wrapper" class="clearfix">	
	
<?php do_action('minimal_menu'); ?>

<div id="page-content-wrapper" class="clearfix custom-background">

<?php 

	$layout = minimalizr_get_meta( 'minimalizr_width' );
	$is_full_width = $layout === 'full' && ( is_page() || is_single() );

	if ( $is_full_width ) {
		$heading_tag = is_front_page() ? 'h2' : 'h1';
		$title       = sprintf(
			'<%1$s class="entry-title">%2$s</%1$s>',
			$heading_tag,
			wp_kses_post( get_the_title() )
		);
		$description = '';

		if ( is_singular() && has_excerpt() ) {
			$excerpt = get_the_excerpt();

			if ( ! empty( $excerpt ) ) {
				$description = sprintf(
					'<p itemprop="description" class="large bottom-10">%s</p>',
					wp_kses_post( $excerpt )
				);
			}
		}

		echo wp_kses_post( '<div class="minimal-box text-center"><div class="container">' . $title . $description . '</div></div>' );
	}

	if ( $is_full_width )
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

<div id="content" class="site-content clearfix <?php echo esc_attr( $layoutwidth ); ?>" style="max-width: <?php echo esc_attr( $max_width ); ?>" >
