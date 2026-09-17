<?php


if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Taxonomies {

	function __construct()
	{
		$this->project_post_types = [];

		add_action('plugins_loaded', function (): void {
			if (defined('DYNAMICPACKAGES_VERSION')) {
				$this->project_post_types[] = 'packages';
			}

			if(defined('DYNAMICAVIATION_VERSION')) {
				$this->project_post_types[] = 'aircrafts';
			}
		});		

        add_action('init', [$this, 'register_taxonomies'], 10);

		//Strings inside add_tax_to_pll or get_taxonomies_arr must must not be translated to avoid _load_textdomain_just_in_time error
		add_filter( 'pll_get_taxonomies', [$this, 'add_tax_to_pll'], 10, 2);

    }

	public function get_taxonomies_arr()
	{
		//forces custom taxonomies to polylang. 

		return [
			'package_location' => [
				'name' => __( 'Locations', 'dycore'),
				'singular_name' => __( 'Location', 'dycore'),
				'emoji' => '🌎',
				'public' => true
			],
			'package_category' => [
				'name' => __( 'Categories', 'dycore'),
				'singular_name' => __( 'Category', 'dycore'),
				'emoji' => '🏷️',
				'public' => true
			],
			'package_included' => [
				'name' => __( 'Included', 'dycore'),
				'singular_name' => __( 'Included', 'dycore'),
				'emoji' => '🍹',
				'public' => false		
			],
			'package_not_included' => [
				'name' => __( 'Not Included', 'dycore'),
				'singular_name' => __( 'Not Included', 'dycore'),
				'emoji' => '❌',
				'public' => false
			],
			'package_terms_conditions' => [
				'name' => __( 'Terms & Conditions', 'dycore'),
				'singular_name' => __( 'Terms & Conditions', 'dycore'),
				'emoji' => '📄',
				'public' => true
			],
			'package_add_ons' => [
				'name' => __( 'Add-ons', 'dycore'),
				'singular_name' => __( 'Add-on', 'dycore'),
				'emoji' => '🤑',
				'public' => false
			]
		];
	}

	public function register_taxonomies()
	{
		foreach ($this->get_taxonomies_arr() as $taxonomy => $config) {
			$singular = $config['singular_name'];
			$plural   = $config['name'];

			$labels = [
				...$config,
				'search_items'      => sprintf(__('Search %s', 'dycore'), $plural),
				'all_items'         => sprintf(__('All %s', 'dycore'), $plural),
				'parent_item'       => sprintf(__('Parent %s', 'dycore'), $singular),
				'parent_item_colon' => sprintf(__('Parent %s', 'dycore'), $singular),
				'edit_item'         => sprintf(__('Edit %s', 'dycore'), $singular),
				'update_item'       => sprintf(__('Update %s', 'dycore'), $singular),
				'add_new_item'      => sprintf(__('Add New %s', 'dycore'), $singular),
				'new_item_name'     => sprintf(__('New %s Name', 'dycore'), $singular),
				'menu_name'         => sprintf('%s %s', $config['emoji'], $plural),
			];

			register_taxonomy($taxonomy, $this->project_post_types, [
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => (bool) $config['public'],
				'show_in_rest'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => true,
			]);
		}
	}

	public function add_tax_to_pll($taxonomies, $is_settings)
	{
		//forces custom taxonomies to polylang. 
		//Strings inside add_tax_to_pll or get_taxonomies_arr must must not be translated to avoid _load_textdomain_just_in_time error

		if(!is_array($taxonomies))
		{
			return false;
		}

		$custom_taxonomies = $this->get_taxonomies_arr();

		foreach($custom_taxonomies as $k => $v)
		{
			$taxonomies[$k] = $k;
		}

		return $taxonomies;
	}

}


?>