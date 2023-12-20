<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Orders {

    function __construct()
    {
		$this->name = 'dy-orders';
        add_action('init', array(&$this, 'package_post_type'));
    }

	public function package_post_type() {

	
		$labels = array(
			'name' => __( 'Orders', 'dynamicpackages' ),
			'singular_name' => __( 'Order', 'dynamicpackages' ),
			'menu_name' => __( 'Orders', 'dynamicpackages' ),
			'name_admin_bar' => __( 'Order', 'dynamicpackages' ),
			'parent_item_colon' => __( 'Parent Order:', 'dynamicpackages' ),
			'all_items' => __( 'All Orders', 'dynamicpackages' ),
			'add_new_item' => __( 'Add New Order', 'dynamicpackages' ),
			'add_new' => __( 'Add New', 'dynamicpackages' ),
			'new_item' => __( 'New Order', 'dynamicpackages' ),
			'edit_item' => __( 'Edit Order', 'dynamicpackages' ),
			'update_item' => __( 'Update Order', 'dynamicpackages' ),
			'view_item' => __( 'View Order', 'dynamicpackages' ),
			'search_items' => __( 'Search Order', 'dynamicpackages' ),
			'not_found' => __( 'Not found', 'dynamicpackages' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'dynamicpackages' ),
			'locations_list' => __( 'Orders list', 'dynamicpackages' ),
			'locations_list_navigation' => __( 'Orders list navigation', 'dynamicpackages' ),
			'filter_items_list' => __( 'Filter locations list', 'dynamicpackages' ),
		);
		
		$args = array(
			'label' => __( 'Order', 'dynamicpackages' ),
			'description' => __( 'Order Description', 'dynamicpackages' ),
			'labels' => $labels,
			'supports' => array( 'title'),
			'hierarchical' => true,
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_position' => 5,
			'show_in_rest' => true,
			'show_in_admin_bar' => true,
			'show_in_nav_menus' => true,
			'can_export' => true,
			'has_archive' => false,
			'exclude_from_search' => false,
			'publicly_queryable' => true,
			'capability_type' => 'page',
			'menu_icon' => 'dashicons-cart'
		);
		
		register_post_type( $this->name, $args );

	}

}

?>