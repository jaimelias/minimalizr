<?php

if (!defined('WPINC')) exit;


#[AllowDynamicProperties]
class Dynamic_Core_WP_JSON
{
	
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_rest_routes_core_args'));
        add_action('rest_api_init', [$this, 'register_rest_routes_transactions']);
    }

	public function register_rest_routes_transactions() {
		$dy_id_param = [
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function($value) {
				return is_numeric($value);
			},
		];

		$dy_request_param = [
			'required'          => true,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function($value) {
				return $value && is_string($value);
			},
		];

		$email_param = [
			'required'          => true,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function($value) {
				return is_string($value) && is_email($value);
			},
		];

		$turnstile = [
			'required' => true,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function($value) {
				return is_string($value);
			},
		];

		$action = [
			'required' => true,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function($value) {
				return is_string($value);
			},
		];
		
		register_rest_route(
			'dy-core',
			sprintf('/%s/(?P<dy_id>\d+)', DY_CORE_TX_SIGN_SLUG),
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [
					$this,
					'tx_endpoint'
				],
				'permission_callback' => '__return_true',
				'args'=> [
					'dy_id' => $dy_id_param,
					'email' => $email_param,
					'dy_request' => $dy_request_param,
					'cf-turnstile-response' => $turnstile,
					'action' => $action,
				],
			]
		);
	}


	public function tx_endpoint($request)
	{

		$turnstile = $request['cf-turnstile-response'];
		$action = $request['action'];

		if(!validate_turnstile($turnstile, $action)) {
			return $this->rest_response(
				[
					'code'    => 'invalid_turnstile_token',
					'message' => 'Invalid Request.',
					'data'    => ['status' => 400],
				],
				404
			);	
		}

		$dy_id = absint($request['dy_id']);
		$email = dy_sanitize_email(trim((string) $request['email']));
		$dy_request = sanitize_key($request['dy_request']);
		
		$post = get_post($dy_id);

		$is_readable = $post instanceof WP_Post
			&& (in_array($post->post_type, ['packages', 'aircrafts']) || $dy_request === 'contact')
			&& (
				is_post_publicly_viewable($post)
				|| current_user_can('read_post', $dy_id)
			);

		if (!$is_readable) {
			return $this->rest_response(
				[
					'code'    => 'invalid_post_id',
					'message' => 'Package not found.',
					'data'    => ['status' => 404],
				],
				404
			);
		}

		$all_dy_request_types = dy_utilities::all_dy_request_types();

		if(!in_array($dy_request, $all_dy_request_types, true)) {
			return $this->rest_response(
				[
					'code'    => 'invalid_dy_request',
					'message' => 'Invalid Request.',
					'data'    => ['status' => 400],
				],
				404
			);
		}


		$tx_id = wp_generate_uuid4();
		$transaction_created = dy_tx::create(
			$tx_id,
			[
				'dy_request' => $dy_request,
				'email'      => $email,
				'dy_id'      => $dy_id,
			]
		);

		if (! $transaction_created) {
			return $this->rest_response(
				[
					'code'    => 'transaction_not_created',
					'message' => 'Unable to start transaction.',
					'data'    => ['status' => 503],
				],
				503
			);
		}

		$output = [
			'tx_id' => $tx_id
		];

		return $this->rest_response($output);
	}

    public function register_rest_routes_core_args()
    {
        register_rest_route('dy-core', 'args', array(
            'methods' => 'GET',
            'callback' => array($this, 'core_args_callback'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route(
            'dy-core',
            '/country-codes/(?P<country_code>[a-z]{2})',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'country_codes_cb'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'country_code' => array(
                        'required'          => true,
                        'validate_callback' => static function($value) {
                            return is_string($value)
                                && 1 === preg_match('/^[a-z]{2}$/', $value);
                        },
                    ),
                ),
            )
        );
    }

    public function core_args_callback($req)
    {
        $site_time = get_site_time();

        $args = apply_filters('dy_core_wp_json_args', []);
        
        $args['dy_nonce'] = wp_create_nonce('dy_nonce');

        foreach($site_time as $k => $v)
        {
            $args[$k] = $v;
        }

        $result = new WP_REST_Response($args, 200);

        $result->set_headers(array(
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ));

        return $result;
    }

    public function country_codes_cb($request)
    {
        $country_code = (string) $request['country_code'];
        $file_path = dirname(__DIR__)
            . 'json/countries/'
            . $country_code
            . '.json';

        if (!is_readable($file_path)) {
            return new WP_Error(
                'dy_core_countries_not_found',
                'Country data was not found.',
                array('status' => 404)
            );
        }

        $countries = wp_json_file_decode(
            $file_path,
            array('associative' => true)
        );

        if (!is_array($countries)) {
            return new WP_Error(
                'dy_core_countries_invalid_json',
                'Country data could not be decoded.',
                array('status' => 500)
            );
        }

        $response = rest_ensure_response($countries);

        // Long browser cache + edge cache (Cloudflare respects s-maxage)
        $response->header(
            'Cache-Control',
            'public, max-age=86400, s-maxage=2592000, immutable'
        );

        // ETag based on file content — lets clients/CDN revalidate cheaply
        $etag = '"' . md5_file($file_path) . '"';
        $response->header('ETag', $etag);

        // Last-Modified based on file mtime
        $response->header(
            'Last-Modified',
            gmdate('D, d M Y H:i:s', filemtime($file_path)) . ' GMT'
        );

        // Prevent Cloudflare from skipping cache due to a stray Set-Cookie
        header_remove('Set-Cookie');

        return $response;
    }

	private function rest_response($data, $status = 200)
	{
		$response = new WP_REST_Response($data, $status);

		$headers = wp_get_nocache_headers();
		unset($headers['Last-Modified']);

		// Retained for older HTTP/1.0 clients and intermediaries.
		$headers['Pragma'] = 'no-cache';

		$response->set_headers($headers);

		return $response;
	}

}

?>
