<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Clients {

    function __construct()
    {
		$this->name = 'dy-clients';
        add_action('init', array(&$this, 'package_post_type'));
    }

	public function package_post_type() {

		$labels = array(
			'name' => __( 'Clients', 'dynamicpackages' ),
			'singular_name' => __( 'Client', 'dynamicpackages' ),
			'menu_name' => __( 'Clients', 'dynamicpackages' ),
			'name_admin_bar' => __( 'Client', 'dynamicpackages' ),
			'parent_item_colon' => __( 'Parent Client:', 'dynamicpackages' ),
			'all_items' => __( 'All Clients', 'dynamicpackages' ),
			'add_new_item' => __( 'Add New Client', 'dynamicpackages' ),
			'add_new' => __( 'Add New', 'dynamicpackages' ),
			'new_item' => __( 'New Client', 'dynamicpackages' ),
			'edit_item' => __( 'Edit Client', 'dynamicpackages' ),
			'update_item' => __( 'Update Client', 'dynamicpackages' ),
			'view_item' => __( 'View Client', 'dynamicpackages' ),
			'search_items' => __( 'Search Client', 'dynamicpackages' ),
			'not_found' => __( 'Not found', 'dynamicpackages' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'dynamicpackages' ),
			'locations_list' => __( 'Clients list', 'dynamicpackages' ),
			'locations_list_navigation' => __( 'Clients list navigation', 'dynamicpackages' ),
			'filter_items_list' => __( 'Filter locations list', 'dynamicpackages' ),
		);
		
		$args = array(
			'label' => __( 'Client', 'dynamicpackages' ),
			'description' => __( 'Client Description', 'dynamicpackages' ),
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