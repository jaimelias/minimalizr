<?php 


if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_WP_JSON {


    public function __construct()
    {
        add_action( 'rest_api_init', array(&$this, 'core_args') );
    }

    public function core_args()
    {
        register_rest_route( 'dy-core', 'args', array(
            'methods' => 'GET',
            'callback' => array(&$this, 'core_args_callback'),
            'permission_callback' => '__return_true'
        ));
    }

    public function core_args_callback($req)
    {

        $timezone = get_option('timezone_string');

        if (empty($timezone)) {
            $timezone = 'UTC';
        }
        
        // Create a DateTimeZone object
        $datetime_zone = new DateTimeZone($timezone);

        // Get the UTC offset in seconds
        $utc_offset_seconds = $datetime_zone->getOffset(new DateTime());

        // Convert seconds to hours and minutes
        $utc_offset_hours = floor($utc_offset_seconds / 3600);
        $utc_offset_minutes = abs(($utc_offset_seconds % 3600) / 60);

        // Format the UTC offset
        $utc_offset = sprintf('%+03d:%02d', $utc_offset_hours, $utc_offset_minutes);


        $result = new WP_REST_Response(array(
            'dy_nonce' => wp_create_nonce('dy_nonce'),
            'utc_date_time' => date('Y-m-d H:i:s', time()),
            'utc' => time(),
            'site_timezone' => $timezone,
            'site_utc_offset' => $utc_offset
        ), 200);


        $result->set_headers(array(
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ));
    
        return $result;
    }
}

?>