<?php

#[AllowDynamicProperties]
class Dynamic_Core_Orders_Metaboxes {

    public function __construct($valid_order_status, $valid_order_status_labels) {
        $this->valid_order_status = $valid_order_status;
        $this->valid_order_status_labels = $valid_order_status_labels;
        add_action('add_meta_boxes', array(&$this, 'add_metaboxes'));
        add_action('save_post', array($this, 'save_metabox_data'));
    }

    public function add_metaboxes() {
        add_meta_box(
            'dy_orders_metabox',          // Unique ID
            'Orders Details',          // Box title
            array($this, 'render_metaboxes'),    // Content callback, must be of type callable
            'dy-orders'                   // Post type
        );
    }



    public function render_metaboxes($post) {
        // Add nonce for security and authentication.
        wp_nonce_field('dy_orders_nonce_action', 'dy_orders_nonce');

        // Retrieve an existing value from the database.
        $order_status = get_post_meta($post->ID, 'dy_order_status', true);
        $order_metadata = json_decode(html_entity_decode(get_post_meta($post->ID, 'dy_order_metadata', true)), true);

        echo $this->select_options_arr('dy_order_status', $order_status, $this->valid_order_status, $this->valid_order_status_labels, 'Order Status');
        
        echo '<br/><br/>';

        echo '<table class="widefat" style="width:100%;">';
        foreach ($order_metadata as $key => $value) {

            //$decoded_value = json_decode('"' . $value . '"');

            echo '<tr>';
            echo "<td>$key</td>";
            echo "<td>$value</td>";
            echo '</tr>';
        }
        echo '</table>';

    }

    public function save_metabox_data($post_id) {
        // Verify the nonce before proceeding.
        if (!isset($_POST['dy_orders_nonce']) || !wp_verify_nonce($_POST['dy_orders_nonce'], 'dy_orders_nonce_action')) {
            return;
        }

        // Stop WordPress from clearing custom fields on autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'dy-orders' == $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        } else {
            return;
        }

        // Sanitize user input.
        $order_status = sanitize_text_field($_POST['dy_order_status']);

        // Update the meta field in the database.
        update_post_meta($post_id, 'dy_order_status', $order_status);
    }

    public function select_options_arr($name, $value, $options, $option_labels, $label)
    {
        // Form fields for entering data.
        $output = '<label for="'.esc_attr($name).'">'.esc_html($label).':</label>';
        $output .= '<select id="'.esc_attr($name).'" name="'.esc_attr($name).'">';
        for($x = 0; $x < count($options); $x++) {
            $option = $options[$x];
            $option_text = $option_labels[$x];
            $selected = ($value == $option) ? 'selected="selected"' : '';
            $output .= '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option_text) . '</option>';
        }
        $output .= '</select>';

        return  $output;
    }

}
