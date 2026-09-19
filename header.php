<?php if ( ! defined( 'WPINC' ) ) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="profile" href="https://gmpg.org/xfn/11">
<link rel="home" href="<?php echo esc_url(home_url('/')); ?>" />
<?php do_action('minimal_menu_css'); ?>
<?php wp_head(); ?>
</head>


<body <?php body_class(); ?>>

<?php wp_body_open(); ?>

<?php do_action('minimal_pre_body'); ?>

<div id="minimal-wrapper" class="clearfix">	
	
<?php do_action('minimal_menu'); ?>
<?php do_action('minimal_site_alert'); ?>

<div id="page-content-wrapper" class="clearfix custom-background">

<?php 

	$layout = minimalizr_get_meta( 'minimalizr_width' );
	$render_minimal_box = minimalizr_get_meta( 'minimalizr_box' ) === 'render' && ( is_page() || is_single() );

	if ( $render_minimal_box ) {
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
					$excerpt
				);
			}
		}

		echo sprintf( 
			'<div class="minimal-box text-center"><div class="container">%s%s</div></div>',
			$title,
			$description
		);
	}

	$layoutwidth = match ( $layout ) {
		'full'  => 'layoutfull',
		'fluid' => 'layoutfixed container-fluid',
		default => 'layoutfixed container',
	};

?>

<div id="content" class="site-content clearfix <?php echo esc_attr( $layoutwidth ); ?>" >
