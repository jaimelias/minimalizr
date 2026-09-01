<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dy_Mailer
{
	public function __construct()
	{
		$this->recipients_limit = 10;
		$this->api_endpoint = 'https://api.sendgrid.com/v3/mail/send';
		$this->web_api_key = get_option('sendgrid_web_api_key');
		$this->email = get_option('sendgrid_email');
		$this->email_to = get_option('sendgrid_email_to');//stored one per line
		$this->email_cc = get_option('sendgrid_email_cc'); //stored one per line
		$this->email_bcc = get_option('sendgrid_email_bcc'); //stored one per line

		$this->name = (get_option('sendgrid_name')) ? get_option('sendgrid_name') : get_bloginfo('name');
		$this->settings_title = 'Mailer Config';
		$this->init();
	}
	

	public function is_enabled()
	{
		$output = ($this->web_api_key && is_email($this->email)) ? true : false;
		
		return $output;
	}

	public function init()
	{
		add_action('admin_init', array($this, 'settings_init'), 1);
		add_action('admin_menu', array($this, 'add_settings_page'), 1);
		
		if($this->is_enabled()) {
			add_filter(
				'pre_wp_mail',
				array($this, 'pre_wp_mail'),
				10,
				2
			);
		}

	}

	
	public function add_settings_page()
	{
		add_submenu_page( 'options-general.php', $this->settings_title, $this->settings_title, 'manage_options', 'sendgrid-api-mailer', array($this, 'settings_page'));
	}	

	public function settings_page()
	{ 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php echo esc_html($this->settings_title); ?></h1>	
			<?php
			settings_fields( 'mailer_settings' );
			do_settings_sections( 'mailer_settings' );
			submit_button();
			?>			
		</form>
		
		<?php
	}		
	
	public function settings_init()
	{

		//mailer settings
		register_setting('mailer_settings', 'sendgrid_email', 'sanitize_text_field');
		register_setting('mailer_settings', 'sendgrid_email_bcc', 'dy_sanitize_email_per_line');
		register_setting('mailer_settings', 'sendgrid_name', 'sanitize_text_field');		


		//sendgrid settings
		register_setting('mailer_settings', 'sendgrid_web_api_key', 'sanitize_user');

		add_settings_section(
			'mailer_settings_section', 
			$this->settings_title, 
			'', 
			'mailer_settings'
		);

		add_settings_section(
			'sendgrid_settings_section', 
			'Sendgrid', 
			'', 
			'mailer_settings'
		);		
		
		add_settings_field( 
			'sendgrid_email', 
			'Bot Email (From)', 
			['dy_input_option', 'email'], 
			'mailer_settings', 
			'mailer_settings_section',
			[
				'key' => 'sendgrid_email'
			] 
		);
		
		add_settings_field( 
			'sendgrid_email_bcc', 
			'Inbox Email (Bcc)', 
			['dy_textarea_option', 'text'], 
			'mailer_settings', 
			'mailer_settings_section',
			[
				'key' => 'sendgrid_email_bcc',
				'rows' => 10,
				'cols' => 50,
				'klass' => 'width-100'
			]
		);			

		add_settings_field( 
			'sendgrid_name', 
			'From Name', 
			['dy_input_option', 'text'], 
			'mailer_settings', 
			'mailer_settings_section',
			[
				'key' => 'sendgrid_name'
			]
		);	

		add_settings_field( 
			'sendgrid_web_api_key', 
			'Web API Key', 
			['dy_input_option', 'text'], 
			'mailer_settings', 
			'sendgrid_settings_section',
			[
				'key' => 'sendgrid_web_api_key'
			]
		);
		
	}

	public function pre_wp_mail($preempt, $atts)
	{
		// Respect another transport that already preempted wp_mail().
		if (null !== $preempt) {
			return $preempt;
		}

		$mail_data = $this->mail_data_defaults($atts);

		try {
			//silences the script termination when the user aborts the request (e.g., closes the browser)
			ignore_user_abort(true);
			
			$payload = $this->build_sendgrid_payload($atts);

			if (is_wp_error($payload)) {
				return $this->report_mail_failure($payload, $mail_data);
			}

			//silences the curl_exec() output to the browser and flushes the output buffer
			while (ob_get_level() > 0) {
				ob_end_flush();
			}
			flush();

			$response = wp_remote_post(
				$this->api_endpoint,
				array(
					'timeout' => 45,
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->web_api_key,
						'Content-Type'  => 'application/json',
						'Accept'        => 'application/json',
					),
					'body' => wp_json_encode($payload),
				)
			);

			if (is_wp_error($response)) {
				$error = new WP_Error(
					'sendgrid_transport_error',
					$response->get_error_message()
				);

				return $this->report_mail_failure($error, $mail_data);
			}

			$status_code = wp_remote_retrieve_response_code($response);

			// The production Mail Send success response is specifically 202.
			if (202 !== $status_code) {
				$response_body = wp_remote_retrieve_body($response);

				$error = new WP_Error(
					'sendgrid_rejected_mail',
					sprintf(
						'SendGrid rejected the mail request with HTTP %d.',
						$status_code
					),
					array(
						'status_code'   => $status_code,
						'response_body' => substr($response_body, 0, 4000),
					)
				);

				return $this->report_mail_failure($error, $mail_data);
			}

			/*
			* Core will not fire this action because pre_wp_mail short-circuits
			* wp_mail(). Fire it here to preserve mail observability.
			*/
			do_action('wp_mail_succeeded', $mail_data);

			return true;
		} finally {
			/*
			* Only remove temporary files owned by this particular mail.
			* Do not glob and delete every temp_* file in the uploads directory.
			*/
			$this->cleanup_owned_temp_attachments(
				isset($atts['attachments']) ? $atts['attachments'] : array()
			);
		}
	}

	private function mail_data_defaults($atts)
	{
		return array(
			'to'          => isset($atts['to']) ? $atts['to'] : array(),
			'subject'     => isset($atts['subject']) ? $atts['subject'] : '',
			'message'     => isset($atts['message']) ? $atts['message'] : '',
			'headers'     => isset($atts['headers']) ? $atts['headers'] : array(),
			'attachments' => isset($atts['attachments'])
				? $atts['attachments']
				: array(),
			'embeds'      => isset($atts['embeds'])
				? $atts['embeds']
				: array(),
		);
	}

	private function report_mail_failure($error, $mail_data)
	{
		if (!is_wp_error($error)) {
			$error = new WP_Error(
				'sendgrid_mail_failed',
				'Unknown SendGrid mail failure.'
			);
		}

		$error->add_data($mail_data, 'mail_data');

		do_action('wp_mail_failed', $error);

		return false;
	}

	private function build_sendgrid_payload($atts)
	{
		$headers = $this->parse_mail_headers(
			isset($atts['headers']) ? $atts['headers'] : array()
		);

		/*
		* Recipients supplied by the individual wp_mail() call.
		*/
		$mail_to = $this->parse_addresses(
			isset($atts['to']) ? $atts['to'] : array()
		);

		$mail_cc = $this->parse_addresses(
			isset($headers['cc']) ? $headers['cc'] : array()
		);

		$mail_bcc = $this->parse_addresses(
			isset($headers['bcc']) ? $headers['bcc'] : array()
		);

		/*
		* Business-operation recipients stored one address per line.
		* Preserve the original helper's sanitization, uniqueness,
		* and 10-address limit.
		*/
		$config_to = $this->parse_addresses(
			$this->email_str_row_to_array($this->email_to)
		);
		$config_cc = $this->parse_addresses(
			$this->email_str_row_to_array($this->email_cc)
		);
		$config_bcc = $this->parse_addresses(
			$this->email_str_row_to_array($this->email_bcc)
		);

		/*
		* Append configured business recipients to the corresponding
		* recipients from wp_mail().
		*/
		$to = array_merge($mail_to, $config_to);
		$cc = array_merge($mail_cc, $config_cc);
		$bcc = array_merge($mail_bcc, $config_bcc);

		/*
		* SendGrid rejects the same email address appearing more than once
		* in a personalization. Give roles the precedence To → CC → BCC.
		*/
		$seen = array();

		$to = $this->unique_addresses($to, $seen);
		$cc = $this->unique_addresses($cc, $seen);
		$bcc = $this->unique_addresses($bcc, $seen);

		if (empty($to)) {
			return new WP_Error(
				'sendgrid_missing_recipient',
				'No valid To recipient was supplied.'
			);
		}

		$subject = isset($atts['subject'])
			? trim((string) $atts['subject'])
			: '';

		if ('' === $subject) {
			return new WP_Error(
				'sendgrid_missing_subject',
				'SendGrid requires a non-empty subject.'
			);
		}

		$message = isset($atts['message'])
			? (string) $atts['message']
			: '';

		if ('' === $message) {
			return new WP_Error(
				'sendgrid_missing_content',
				'SendGrid requires non-empty message content.'
			);
		}

		$personalization = array('to' => $to);

		if (!empty($cc)) {
			$personalization['cc'] = $cc;
		}

		if (!empty($bcc)) {
			$personalization['bcc'] = $bcc;
		}

		$content_type = $this->resolve_content_type($headers);


		if ('text/html' === $content_type) {
			$minified_message = $this->minify_html($message);

			/*
			* preg_replace() can theoretically return null on an error.
			* Preserve delivery reliability by falling back to the original body.
			*/
			if (is_string($minified_message)) {
				$message = $minified_message;
			}
		}

		$payload = array(
			'personalizations' => array($personalization),
			'from' => array(
				'email' => $this->email,
				'name'  => $this->name,
			),
			'subject' => $subject,
			'content' => array(
				array(
					'type'  => $content_type,
					'value' => $message,
				),
			),
		);

		$reply_to = $this->parse_addresses(
			isset($headers['reply-to'])
				? $headers['reply-to']
				: array()
		);

		if (1 === count($reply_to)) {
			$payload['reply_to'] = $reply_to[0];
		} elseif (count($reply_to) > 1) {
			$payload['reply_to_list'] = $reply_to;
		}

		$attachments = $this->build_sendgrid_attachments(
			isset($atts['attachments']) ? $atts['attachments'] : array(),
			'attachment'
		);

		if (is_wp_error($attachments)) {
			return $attachments;
		}

		$embeds = $this->build_sendgrid_embeds(
			isset($atts['embeds']) ? $atts['embeds'] : array()
		);

		if (is_wp_error($embeds)) {
			return $embeds;
		}

		$attachments = array_merge($attachments, $embeds);

		if (!empty($attachments)) {
			$payload['attachments'] = $attachments;
		}

		return $payload;
	}

	private function parse_mail_headers($headers)
	{
		if (empty($headers)) {
			return array();
		}

		if (!is_array($headers)) {
			$headers = preg_split('/\r\n|\r|\n/', (string) $headers);
		}

		$parsed = array();

		foreach ($headers as $key => $line) {
			if (is_string($key)) {
				$name  = $key;
				$value = $line;
			} else {
				if (!is_string($line) || false === strpos($line, ':')) {
					continue;
				}

				list($name, $value) = explode(':', $line, 2);
			}

			$name  = strtolower(trim($name));
			$value = trim((string) $value);

			if ('' === $name || '' === $value) {
				continue;
			}

			if (!isset($parsed[$name])) {
				$parsed[$name] = array();
			}

			$parsed[$name][] = $value;
		}

		return $parsed;
	}

	private function resolve_content_type($headers)
	{
		$content_type = 'text/plain';

		if (!empty($headers['content-type'][0])) {
			$parts = explode(';', $headers['content-type'][0], 2);
			$content_type = strtolower(trim($parts[0]));
		}

		/*
		* Because core will not reach this filter after preemption, apply it here
		* for compatibility with existing WordPress integrations.
		*/
		$content_type = apply_filters(
			'wp_mail_content_type',
			$content_type
		);

		if (!in_array($content_type, array('text/plain', 'text/html'), true)) {
			$content_type = 'text/plain';
		}

		return $content_type;
	}


	private function unique_addresses($addresses, &$seen)
	{
		$output = array();

		foreach ($addresses as $address) {
			if (
				!isset($address['email'])
				|| !is_email($address['email'])
			) {
				continue;
			}

			$key = strtolower($address['email']); 

			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$output[]   = $address;
		}

		return $output;
	}

	private function build_sendgrid_attachments(
		$attachments,
		$disposition = 'attachment'
	) {
		if (empty($attachments)) {
			return array();
		}

		if (is_string($attachments)) {
			$attachments = explode(
				"\n",
				str_replace(array("\r\n", "\r"), "\n", $attachments)
			);
		} elseif (!is_array($attachments)) {
			return new WP_Error(
				'sendgrid_invalid_attachments',
				'Attachments must be supplied as a file path or an array of file paths.'
			);
		}

		if (!in_array($disposition, array('attachment', 'inline'), true)) {
			return new WP_Error(
				'sendgrid_invalid_attachment_disposition',
				'Attachment disposition must be either attachment or inline.'
			);
		}

		$output = array();

		foreach ($attachments as $filename => $path) {
			if (!is_string($path)) {
				return new WP_Error(
					'sendgrid_invalid_attachment_path',
					'Every attachment path must be a string.'
				);
			}

			$path = trim($path);

			// Ignore empty lines in the string form accepted by wp_mail().
			if ('' === $path) {
				continue;
			}

			$filename = is_string($filename)
				? wp_basename(trim($filename))
				: wp_basename($path);

			if ('' === $filename) {
				return new WP_Error(
					'sendgrid_missing_attachment_filename',
					'SendGrid requires every attachment to have a filename.'
				);
			}

			if (preg_match('/[;,\r\n]/', $filename)) {
				return new WP_Error(
					'sendgrid_invalid_attachment_filename',
					'Attachment filenames cannot contain semicolons, commas, or line breaks.'
				);
			}

			if (!is_file($path) || !is_readable($path)) {
				return new WP_Error(
					'sendgrid_unreadable_attachment',
					sprintf(
						'Attachment "%s" is not a readable file.',
						$filename
					),
					array('path' => $path)
				);
			}

			$contents = @file_get_contents($path);

			if (false === $contents || '' === $contents) {
				return new WP_Error(
					'sendgrid_attachment_read_error',
					sprintf(
						'Attachment "%s" could not be read or is empty.',
						$filename
					),
					array('path' => $path)
				);
			}

			$filetype = wp_check_filetype($filename);

			if (empty($filetype['type'])) {
				$filetype = wp_check_filetype($path);
			}

			$mime_type = !empty($filetype['type'])
				? $filetype['type']
				: 'application/octet-stream';

			if (preg_match('/[;,\r\n]/', $mime_type)) {
				return new WP_Error(
					'sendgrid_invalid_attachment_mime_type',
					'Attachment MIME types cannot contain semicolons, commas, or line breaks.'
				);
			}

			$output[] = array(
				'content'     => base64_encode($contents),
				'type'        => $mime_type,
				'filename'    => $filename,
				'disposition' => $disposition,
			);
		}

		return $output;
	}

	private function build_sendgrid_embeds($embeds)
	{
		if (empty($embeds)) {
			return array();
		}

		if (is_string($embeds)) {
			$embeds = explode(
				"\n",
				str_replace(array("\r\n", "\r"), "\n", $embeds)
			);
		} elseif (!is_array($embeds)) {
			return new WP_Error(
				'sendgrid_invalid_embeds',
				'Embeds must be supplied as a file path or an array of file paths.'
			);
		}

		$output = array();

		foreach ($embeds as $key => $path) {
			if (!is_string($path)) {
				return new WP_Error(
					'sendgrid_invalid_embed_path',
					'Every embed path must be a string.'
				);
			}

			$path = trim($path);

			if ('' === $path) {
				continue;
			}

			/*
			* pre_wp_mail short-circuits WordPress before core applies this
			* filter, so apply it here to preserve wp_mail() embed behavior.
			*/
			$embed_args = apply_filters(
				'wp_mail_embed_args',
				array(
					'path'        => $path,
					'cid'         => (string) $key,
					'name'        => wp_basename($path),
					'encoding'    => 'base64',
					'type'        => '',
					'disposition' => 'inline',
				)
			);

			if (!is_array($embed_args)) {
				return new WP_Error(
					'sendgrid_invalid_embed_args',
					'The wp_mail_embed_args filter must return an array.'
				);
			}

			$path = isset($embed_args['path'])
				&& is_string($embed_args['path'])
				? trim($embed_args['path'])
				: '';

			if ('' === $path || !is_file($path) || !is_readable($path)) {
				return new WP_Error(
					'sendgrid_unreadable_embed',
					'An embedded file is not a readable file.',
					array('path' => $path)
				);
			}

			$content_id = isset($embed_args['cid'])
				&& is_scalar($embed_args['cid'])
				? trim((string) $embed_args['cid'])
				: '';

			if ('' === $content_id) {
				return new WP_Error(
					'sendgrid_missing_embed_content_id',
					'SendGrid requires every inline embed to have a content ID.'
				);
			}

			if (preg_match('/[;\r\n]/', $content_id)) {
				return new WP_Error(
					'sendgrid_invalid_embed_content_id',
					'Embed content IDs cannot contain semicolons or line breaks.'
				);
			}

			$filename = isset($embed_args['name'])
				&& is_string($embed_args['name'])
				? wp_basename(trim($embed_args['name']))
				: '';

			if ('' === $filename) {
				$filename = wp_basename($path);
			}

			if ('' === $filename) {
				return new WP_Error(
					'sendgrid_missing_embed_filename',
					'SendGrid requires every inline embed to have a filename.'
				);
			}

			if (preg_match('/[;,\r\n]/', $filename)) {
				return new WP_Error(
					'sendgrid_invalid_embed_filename',
					'Embed filenames cannot contain semicolons, commas, or line breaks.'
				);
			}

			$contents = @file_get_contents($path);

			if (false === $contents || '' === $contents) {
				return new WP_Error(
					'sendgrid_embed_read_error',
					sprintf(
						'Embedded file "%s" could not be read or is empty.',
						$filename
					),
					array('path' => $path)
				);
			}

			$filetype = isset($embed_args['type'])
				&& is_string($embed_args['type'])
				? trim($embed_args['type'])
				: '';

			if ('' === $filetype) {
				$detected_filetype = wp_check_filetype($filename);

				if (empty($detected_filetype['type'])) {
					$detected_filetype = wp_check_filetype($path);
				}

				$filetype = !empty($detected_filetype['type'])
					? $detected_filetype['type']
					: 'application/octet-stream';
			}

			if (preg_match('/[;,\r\n]/', $filetype)) {
				return new WP_Error(
					'sendgrid_invalid_embed_mime_type',
					'Embed MIME types cannot contain semicolons, commas, or line breaks.'
				);
			}

			$output[] = array(
				'content'     => base64_encode($contents),
				'type'        => $filetype,
				'filename'    => $filename,
				'disposition' => 'inline',
				'content_id'  => $content_id,
			);
		}

		return $output;
	}

	private function normalize_attachment_paths($attachments)
	{
		if (empty($attachments)) {
			return array();
		}

		if (is_string($attachments)) {
			$attachments = explode(
				"\n",
				str_replace(array("\r\n", "\r"), "\n", $attachments)
			);
		} elseif (!is_array($attachments)) {
			return array();
		}

		$paths = array();

		foreach ($attachments as $path) {
			if (!is_string($path)) {
				continue;
			}

			$path = trim($path);

			if ('' !== $path) {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	private function cleanup_owned_temp_attachments($attachments)
	{
		$base_dir = realpath(wp_upload_dir()['basedir']);

		foreach ($this->normalize_attachment_paths($attachments) as $path) {
			$real_path = realpath($path);

			if (
				false === $base_dir
				|| false === $real_path
				|| $base_dir !== dirname($real_path)
				|| 0 !== strpos(basename($real_path), 'temp_')
				|| !is_file($real_path)
			) {
				continue;
			}

			@unlink($real_path);
		}
	}

	private function parse_addresses($addresses)
	{
		if (empty($addresses)) {
			return array();
		}

		$input = is_array($addresses)
			? $addresses
			: array($addresses);

		$output = array();

		foreach ($input as $value) {
			if (!is_string($value)) {
				continue;
			}

			/*
			* A wp_mail recipient/header value can contain comma-separated
			* addresses. Configured one-per-line options are processed by
			* email_str_row_to_array() instead.
			*/
			$candidates = str_getcsv(
				$value,
				',',
				'"',
				'\\'
			);

			foreach ($candidates as $candidate) {
				$candidate = trim($candidate);

				if ('' === $candidate) {
					continue;
				}

				$name = '';
				$email = $candidate;

				if (preg_match('/^(.*)<([^>]+)>$/', $candidate, $matches)) {
					$name = trim(
						$matches[1],
						" \t\n\r\0\x0B\"'"
					);

					$email = trim($matches[2]);
				}

				$email = sanitize_email($email);

				if (!is_email($email)) {
					continue;
				}

				$address = array(
					'email' => $email,
				);

				if ('' !== $name) {
					$address['name'] = sanitize_text_field($name);
				}

				$output[] = $address;
			}
		}

		return $output;
	}


	
	public function minify_html($template)
	{
		$search = array(
			'/\>[^\S ]+/s',
			'/[^\S ]+\</s',
			'/(\s)+/s',
			'/<!--(.|\s)*?-->/'
		);

		$replace = array(
			'>',
			'<',
			'\\1',
			''
		);

		return preg_replace($search, $replace, $template);			
	}


	public function email_str_row_to_array($str)
	{
		$output = [];

		if($str)
		{
			$emails = explode(PHP_EOL, html_entity_decode($str));		
			$output = array_slice(array_unique(array_filter(array_map('sanitize_email', $emails))), 0, $this->recipients_limit);
		}


		return $output;
	}
	
}


?>
