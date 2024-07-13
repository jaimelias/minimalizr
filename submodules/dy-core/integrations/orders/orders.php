<?php

if ( !defined( 'WPINC' ) ) exit;


#[AllowDynamicProperties]
class Dynamic_Core_Orders {

    function __construct()
    {
		$this->name = 'dy-orders';


		$valid_order_status = array(
			'pending', 
			'paid', 
			'confirmed', 
			'postponed', 
			'cancelled'
		);
		$valid_order_status_labels = array(
			__('Pending', 'dynamicpackages'), 
			__('Paid', 'dynamicpackages'), 
			__('Confirmed', 'dynamicpackages'), 
			__('Postponed', 'dynamicpackages'), 
			__('Cancelled', 'dynamicpackages')
		);


		$this->valid_order_status = $valid_order_status;
		$this->valid_order_status_labels = $valid_order_status_labels;

		$this->booking_fields = array(
			'pax_regular',
			'pax_discount',
			'pax_free',
			'booking_date',
			'booking_hour',
			'end_date',
			'return_hour',
			'booking_extra',
			'coupon_code',
			'hash',
		);


        add_action('init', array(&$this, 'package_post_type'));

		require_once(plugin_dir_path( __FILE__ ) . 'orders-metaboxes.php');
		new Dynamic_Core_Orders_Metaboxes($valid_order_status, $valid_order_status_labels);
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


    
	public function create_new_order()
	{
		$post_data = array(
			'post_title'    => 'temp_'.time(),
			'post_type'     => 'dy-orders',
			'post_status'   => 'publish'
		);
		
		return wp_insert_post($post_data);
	}


	public function save_order($data)
	{
        //the order status should be changed in each payment gateway
        $order_status = apply_filters('dy_order_status', 'pending');

        if(!in_array($order_status, $this->valid_order_status))
        {
            wp_die('Invalid order_status: orders.php -> save_order: ' . esc_attr($order_status));
        }
        if(!$this->validate_data($data))
        {
            wp_die('Invalid data: orders.php -> save_order');
        }

		//here i would add the code create a new post or get the orderID

		$unique_id = uniqid();
		$order_id = $this->create_new_order();

		if(!$order_id)
		{
			wp_die('Post Type Not Set: orders.php -> save_order');
		}

        $providers = apply_filters('dy_list_providers', array());

		$metadata = array_merge(
			array(
				'unique_id' => $unique_id,
				'booking_query' => $this->get_booking_query($data)
			), 
			$data
		);

		add_post_meta($order_id, 'dy_order_metadata', json_encode($metadata, JSON_UNESCAPED_UNICODE), true);
		add_post_meta($order_id, 'dy_order_status', $order_status, true);

		// Updates post title with the id of the order and name of the client
		$new_title = $order_id . ' - ' . $data['first_name'] . ' '. $data['lastname']. ' ['.$data['email'].']: '. $data['title'];
		$post_update_data = array(
			'ID'         => $order_id,
			'post_title' => esc_html($new_title),
			'post_name' => 'order-' . $order_id
		);

		wp_update_post($post_update_data);

		return $order_id;
	}

	public function get_booking_query($data)
	{

		$query = array();

		for($x = 0; $x < count($this->booking_fields); $x++)
		{
			$k = $this->booking_fields[$x];

			if(array_key_exists($k, $data))
			{
				$query[$k] = $data[$k];
			}
		}


		return http_build_query($query);

	}

    public function validate_data($data) {
        
        $required_fields = [
			'hash',
            'first_name',
            'lastname',
			'description',
            'add_ons',
            'country_calling_code',
            'phone',
            'email',
            'lang',
            'post_id',
            'package_url',
            'total',
            'outstanding',
            'amount',
            'payment_type',
            'deposit'
        ];
    
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                write_log('Invalid param: orders.php -> validate_data -> '. esc_attr($field));
                return false;
            }
        }
    
        return true;
    }

}

?>