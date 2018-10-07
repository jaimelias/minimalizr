<?php
	remove_shortcode('gallery');
	add_shortcode('gallery', 'parse_gallery_shortcode');
	
	function parse_gallery_shortcode($attr) {
		
		$ids = array();
		$count = 0;
		$size = 'medium';
		$width = get_option($size.'_size_w');
		$height = get_option($size.'_size_h');
		$autoplay = '';
		$alt = '';
		
		if(is_array($attr))
		{
			if(array_key_exists('ids', $attr))
			{
				if($attr['ids'] != '')
				{
					$ids = explode(",", $attr['ids']);
					$count++;
				}	
			}
			if(array_key_exists('size', $attr))
			{
				if($attr['size'] != '' && $attr['size'] != 'medium')
				{
					if(get_option($size.'_size_w') != '' && get_option($size.'_size_h') != '')
					{
						$size = $attr['size'];
						$width = get_option($size.'_size_w');
						$height = get_option($size.'_size_h');
					}
				}
			}
			if(array_key_exists('autoplay', $attr))
			{
				if($attr['autoplay'] == 'true')
				{
					$autoplay = 'autoplay';
				}
			}
			if(array_key_exists('alt', $attr))
			{
				if($attr['alt'] != '')
				{
					$alt = $attr['alt'];
				}
			}			
		}	
		
		if($count > 0)
		{	
			$slideshow = '<div class="slideshow '.esc_html($size).' '.esc_html($autoplay).' bottom-20 relative block border-box">';
			
			for($x = 0; $x < count($ids); $x++)
			{
				$hidden = 'hidden';
				
				if($x == 0)
				{
					$hidden = '';
				}
				
				$image = wp_get_attachment_image_src($ids[$x], $size);
				$slide = '<img class="'.esc_html($hidden).' slide block width-100 img-responsive" width="'.esc_html($image[1]).'" height="'.esc_html($image[2]).'" src="'.esc_url($image[0]).'" />';	
				$slideshow .= ($slide);
			}
			
			if($alt != '')
			{
				$slideshow .= '<span class="alt text-center inline-block strong absolute uppercase">'.esc_html($alt).'</span>';
			}
			
			$slideshow .= '<span class="previous controller pointer inline-block absolute text-center"><i class="fas fa-angle-left"></i></span>';
			$slideshow .= '<span class="next controller pointer inline-block absolute text-center"><i class="fas fa-angle-right"></i></span>';
			
			$slideshow .= '</div>';
		}
		else
		{
			$slideshow = 'Minimalizr gallery error!';
		}
		
		return $slideshow;

	}

	?>