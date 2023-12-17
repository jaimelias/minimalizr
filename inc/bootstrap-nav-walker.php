<?php

/**
 * Class Name: wp_bootstrap_navwalker
 * GitHub URI: https://github.com/twittem/wp-bootstrap-navwalker
 * Description: A custom WordPress nav walker class to implement the Bootstrap 3 navigation style in a custom theme using the WordPress built in menu manager.
 * Version: 2.0.4
 * Author: Edward McIntyre - @twittem
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

#[AllowDynamicProperties]
class wp_bootstrap_navwalker extends Walker_Nav_Menu {


	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul role=\"menu\" class=\" dropdown-menu hidden\">\n";
	}

	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		if ( strcasecmp( $item->attr_title, 'divider' ) == 0 && $depth === 1 ) {
			$output .= $indent . '<li role="presentation" class="divider">';
		} else if ( strcasecmp( $item->title, 'divider') == 0 && $depth === 1 ) {
			$output .= $indent . '<li role="presentation" class="divider">';
		} else if ( strcasecmp( $item->attr_title, 'dropdown-header') == 0 && $depth === 1 ) {
			$output .= $indent . '<li role="presentation" class="dropdown-header">' . esc_attr( $item->title );
		} else if ( strcasecmp($item->attr_title, 'disabled' ) == 0 ) {
			$output .= $indent . '<li role="presentation" class="disabled"><a href="#">' . esc_attr( $item->title ) . '</a>';
		} else {

			$class_names = $value = '';

			$classes = empty( $item->classes ) ? array() : (array) $item->classes;
			$classes[] = 'menu-item-' . $item->ID;

			$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );

			if ( $args->has_children )
				$class_names .= ' dropdown';

			if ( in_array( 'current-menu-item', $classes ) )
				$class_names .= ' active';

			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
			$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

			$output .= $indent . '<li' . $id . $value . $class_names .'>';

			$atts = array();
			$atts['href'] = ! empty( $item->url ) ? $item->url : '';
			$atts['title']  = ! empty( $item->title )	? $item->title	: '';
			$atts['target'] = ! empty( $item->target )	? $item->target	: '';
			$atts['rel']    = ! empty( $item->xfn )		? $item->xfn	: '';
			$atts['attr_title']  = ! empty( $item->attr_title )		? $item->attr_title	: '';
			$atts['class'] = ! empty( $item->class )		? $item->class	: '';

			// If item has_children add atts to a
			if($depth === 0)
			{
				if ( $args->has_children ) {
					$atts['data-toggle']	= 'dropdown';
					$atts['class']			= 'dropdown-toggle';
					$atts['aria-haspopup']	= 'true';
				}
				
				$menu_weight = get_theme_mod('minimalizr_menu_weight');

				if($menu_weight && $depth === 0)
				{
					$atts['class'] .= (!empty($menu_weight)) 
						? (!empty($atts['class'])) 
						? ' '.$menu_weight 
						: $menu_weight 
						 : '';
				}
			}

			

			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args );

			$attributes = '';
			foreach ( $atts as $attr => $value ) {
				if ( ! empty( $value ) && $attr !== "title") {

					if($attr === 'attr_title')
					{
						$attr = 'title';
					}

					$value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' ' . $attr . '="' . $value . '"';
				}
			}

			$item_output = $args->before;
			$item_output .= '<a'. $attributes .'>';
			$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
			$item_output .= ( $args->has_children && 0 === $depth ) ? '<small class="caret inline-block text-muted pull-right">&#9660;</small></a>' : '</a>';
			$item_output .= $args->after;

			$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		}
	}

	public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
        if ( ! $element )
            return;

        $id_field = $this->db_fields['id'];

        // Display this element.
        if ( is_object( $args[0] ) )
           $args[0]->has_children = ! empty( $children_elements[ $element->$id_field ] );

        parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
    }

	public static function fallback($args) {
		if (!current_user_can('manage_options')) {
			return;
		}
	
		$menu_id = $args['menu_id'] ?? '';
		$menu_class = $args['menu_class'] ?? '';
		$items_wrap = $args['items_wrap'] ?? '';

		$fb_output = '';
	
		$fb_output .= '<ul';
	
		if ($menu_id) {
			$fb_output .= ' id="' . esc_attr($menu_id) . '"';
		}
	
		if ($menu_class) {
			$fb_output .= ' class="' . esc_attr($menu_class) . '"';
		}
	
		$fb_output .= '>';
		$fb_output .= '<li><a href="' . esc_url(admin_url('nav-menus.php')) . '"><span class="dashicons dashicons-plus"></span>'.esc_html(__('Menu', 'minimalizr')).'</a></li>';
		$fb_output .= '</ul>';
	
	
		echo sprintf($items_wrap, '', '', $fb_output);
	}
	
	
}
