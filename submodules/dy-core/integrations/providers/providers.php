<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Providers {

	private static $cache = [];

    function __construct()
    {
		$this->name = 'dy-providers';
        $this->handle_create_edit();
		add_filter('dy_list_providers', array(&$this, 'get_providers'));
		add_action('init', array(&$this, 'register_taxonomies'));
		add_action( 'admin_head', array(&$this, 'admin_head') );
    }

	public function admin_head()
	{
		if(isset($_GET['taxonomy']))
		{
			if($_GET['taxonomy'] === $this->name)
			{
				echo '<style>.term-slug-wrap, .term-parent-wrap, .term-description-wrap{display: none;}</style>';
			}
		}
		
	}

	public function register_taxonomies(){

		$taxonomies = array(
			$this->name => array(
				'name' => __( 'Providers'),
				'singular_name' => __( 'Provider'),
				'emoji' => '🤖',
				'public' => false
			)
		);

		foreach($taxonomies as $key => $value)
		{
			$singular = $value['singular_name'];
			$plural = $value['name'];
			$emoji = $value['emoji'];
			$public = $value['public'];
			$labels = $value;
			$labels['search_items'] = sprintf(__('Search %s'), $plural);
			$labels['all_items'] = sprintf(__('All %s'), $plural);
			$labels['parent_item'] = sprintf(__('Parent %s'), $singular);
			$labels['parent_item_colon'] = sprintf(__('Parent %s'), $singular);
			$labels['edit_item'] = sprintf(__('Edit %s'), $singular);
			$labels['update_item'] = sprintf(__('Update %s'), $singular);
			$labels['add_new_item'] = sprintf(__('Add New %s'), $singular);
			$labels['new_item_name'] = sprintf(__('New %s Name'), $singular);
			$labels['menu_name'] = $emoji.' '.$plural;

			$args = array(
				'labels' => $labels,
				'hierarchical' => true,
				'public' => $public,
				'show_in_rest'				=> true,
				'show_ui' => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud' => true
			);

			register_taxonomy($key, array('dy-orders', 'packages', 'aircrafts' ), $args );
		}
	}

    public function handle_create_edit()
    {
        //handles edit and save
		add_action($this->name.'_edit_form_fields', array(&$this, 'form'), 10, 2);
		add_action( 'create_'.$this->name, array(&$this, 'handle_save'), 10, 2);
		add_action( 'edited_'.$this->name, array(&$this, 'handle_save'), 10, 2);
    }

	public function handle_save($term_id) {
		
		if(!current_user_can( 'edit_posts' )) return;
		
		if(isset($_POST[$this->name.'_outstanding_balance']))
		{
			update_term_meta($term_id, $this->name.'_outstanding_balance', sanitize_text_field($_POST[$this->name.'_outstanding_balance']));
		}
		if(isset($_POST[$this->name.'_language']))
		{
			update_term_meta($term_id, $this->name.'_language', sanitize_text_field($_POST[$this->name.'_language']));
		}
		if(isset($_POST[$this->name.'_emails']))
		{
			update_term_meta($term_id, $this->name.'_emails', esc_textarea($this->sanitize_items_per_line('sanitize_email', $_POST[$this->name.'_emails'])));
		}
		if(isset($_POST[$this->name.'_whatsapp']))
		{
			update_term_meta($term_id, $this->name.'_whatsapp', sanitize_text_field($_POST[$this->name.'_whatsapp']));
		}
	}

	public function sanitize_items_per_line($sanitize_func, $str)
	{
		$str = html_entity_decode($str);
		$emails = explode("\r\n", $str);		
		$arr = array_slice(array_unique(array_filter(array_map($sanitize_func, $emails))), 0, 10) ;

		return implode("\r\n", $arr);
	}

	public function textarea_items_per_line($term_id, $sanitize_func = 'sanitize_text_field')
	{
		$emails = get_term_meta($term_id, $this->name.'_emails', true);
		return '<textarea rows="10" name="'.esc_attr($this->name.'_emails').'">'.esc_textarea($this->sanitize_items_per_line($sanitize_func, $emails)).'</textarea>';
	}

	public function input_field($term_id, $type = 'text')
	{
		$name = $this->name.'_whatsapp';
		$value = get_term_meta($term_id, $name, true);
		return '<input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" />';
	}

	public function input_select($term_id, $sufix, $optionsArr = ['0' => 'No', '1' => 'Yes'])
	{
		$options = '';
		$name = $this->name.$sufix;
		$term_value = get_term_meta($term_id, $name, true);

		foreach($optionsArr as $value => $text )
		{
			$selected = (strval($value) === strval($term_value)) ? ' selected ' : '';
			$options .= '<option '.esc_attr($selected).' value="'.esc_attr($value).'">'.esc_html($text).'</option>';
		}
		
		$output = '<select name="'.esc_attr($name).'">'.$options.'</select>';

		return $output;
	}

	public function admin_taxonomy_form_row($name, $label, $field, $description = null)
	{
		if($description)
		{
			$description = '<br/><p class="description">'.esc_html($description).'</p>';
		}
		return '<tr class="form-field"><th scope="row" valign="top"><label for="'.esc_attr($name).'">'.esc_html($label).'</label></th><td>'.$field.$description.'</td></tr>';
	}

    public function form($term)
    {
		$rows = '';
        $term_id = $term->term_id;

		//outstanding balance start
		$rows .= $this->admin_taxonomy_form_row(
			$this->name.'_outstanding_balance', 
			__('Charge balance directy to customer?'), 
			$this->input_select($term_id, '_outstanding_balance', ['0' => __('No'), '1' => __('Yes')])
		);
		//outstanding balance end

		//languages start
		$languages = get_languages();
		$language = get_term_meta($term_id, $this->name.'_language', true);
		$language = ($language) ? $language : current_language();
		$langArr = [];

		for($x = 0; $x < count($languages); $x++)
		{
			$langArr[$languages[$x]] = $languages[$x];
		}

		$rows .= $this->admin_taxonomy_form_row(
			$this->name.'_language', 
			__('Provider Language'), 
			$this->input_select($term_id, '_language', $langArr)
		);
		//languages end


		$rows .= $this->admin_taxonomy_form_row($this->name.'_whatsapp', __('Whatsapp'), $this->input_field($term_id, 'number'));
		$rows .= $this->admin_taxonomy_form_row($this->name.'_emails', __('Provider Emails'), $this->textarea_items_per_line($term_id, 'sanitize_email'), __('1 email per line. Up to 10 emails maximum.'));
		echo $rows;
    }

	public function email_str_row_to_array($str)
	{
		$output = [];

		if($str)
		{
			$emails = explode("\r\n", html_entity_decode($str));		
			$output = array_slice(array_unique(array_filter(array_map('sanitize_email', $emails))), 0, 10);
		}


		return $output;
	}

	public function get_providers($output = array())
	{
		$cache_key = $this->name.'get_providers';

        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

		global $post;

		if($post instanceof WP_Post)
		{
			$terms = get_the_terms($post->ID, $this->name);

			if(empty($terms) || is_wp_error($terms))
			{
				$terms = new stdClass();
			}

			if(property_exists($post, 'post_parent') && $post->post_parent > 0)
			{
				$parent_terms =  get_the_terms($post->post_parent, $this->name);

				if (!empty($parent_terms) && !is_wp_error($parent_terms))
				{
					$terms = (object) array_merge((array) $terms, (array) $parent_terms);
				}
			}

			$term_ids = [];

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) )
			{
				foreach ( $terms as $t )
				{
					if(in_array($t->term_id, $term_ids))
					{
						continue;
					}

					$outstanding_balance = get_term_meta($t->term_id, $this->name . '_outstanding_balance', true);
					$language = get_term_meta($t->term_id, $this->name . '_language', true);
					$emails_str = get_term_meta($t->term_id, $this->name . '_emails', true);
					$emails = $this->email_str_row_to_array($emails_str);
					$whatsapp = get_term_meta($t->term_id, $this->name . '_whatsapp', true);
					
					$row = array(
						'id' => $t->term_id,
						'name' => $t->name,
						'outstanding_balance' => $outstanding_balance,
						'language' => $language,
						'emails' => $emails,
						'whatsapp' => $whatsapp
					);

					$output[] = $row;
					$term_ids[] = $t->term_id;
				}
			}

		}

        self::$cache[$cache_key] = $output;

		return $output;
	}

}

?>