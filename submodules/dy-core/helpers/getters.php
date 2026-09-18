<?php

if(function_exists('dy_get_taxonomies')) {

	function dy_get_taxonomies($term_name) : array {
        static $cache = [];
		global $post;

		$output = [];
		$cache_key = 'dy_get_taxonomies_'.$term_name.'_'.$post->ID;

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

		$the_id = $post->ID;
		
		if($post->post_parent > 0)
		{
			$the_id = $post->post_parent;
		}		
		
		$terms = get_the_terms($the_id, $term_name);

		
		if($terms)
		{
			for($x = 0; $x < count($terms); $x++)
			{
				$output[] = $terms[$x];
			}			
		}

        //store output in $cache
        $cache[$cache_key] = $output;

		return $output;
	}

}

if(!function_exists('dy_get_taxo_names')) {
	function dy_get_taxo_names($term_name, $the_id = null) : array {
		static $cache = [];
		$post = get_post($the_id);

		$cache_key = 'dy_get_taxo_names_'.$term_name.'_'.$post->ID;

		if (array_key_exists($cache_key, $cache)) {
			return $cache[$cache_key];
		}

		$current_terms = get_the_terms($post->ID, $term_name);
		$current_terms = is_array($current_terms) ? $current_terms : [];

		$parent_terms = [];

		if($post->post_parent > 0)
		{
			$parent_terms = get_the_terms($post->post_parent, $term_name);
			$parent_terms = is_array($parent_terms) 
				? $parent_terms 
				: [];
		}

		$terms_by_id = [];

		foreach ([...$current_terms, ...$parent_terms] as $term) {
			if ($term instanceof WP_Term) {
				$terms_by_id[$term->term_id] = $term->name;
			}
		}

		$output = array_values($terms_by_id);

		return $cache[$cache_key] = $output;
	}
}
