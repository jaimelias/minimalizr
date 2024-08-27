<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dy_Mailer
{
	public function __construct()
	{
		$this->email_limit = 10;
		$this->api_endpoint = 'https://api.sendgrid.com/v3/mail/send';
		$this->web_api_key = get_option('sendgrid_web_api_key');
		$this->email = get_option('sendgrid_email');
		$this->email_to = get_option('sendgrid_email_to');
		$this->email_cc = get_option('sendgrid_email_cc');
		$this->email_bcc = get_option('sendgrid_email_bcc');

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
		add_action('admin_init', array(&$this, 'settings_init'), 1);
		add_action('admin_menu', array(&$this, 'add_settings_page'), 1);
		add_filter('wp_mail_from', array(&$this, 'from_email'), 100, 1);
		add_filter('wp_mail_from_name', array(&$this, 'from_name'), 100, 1);
		
		if($this->is_enabled())
		{
			add_action( 'phpmailer_init', array(&$this, 'phpmailer'), 100, 1 );
		}
		else
		{
			add_action( 'phpmailer_init', array(&$this, 'add_bcc_to_phpmailer'), 100, 1 );
		}
	}

	
	public function add_settings_page()
	{
		add_submenu_page( 'options-general.php', $this->settings_title, $this->settings_title, 'manage_options', 'sendgrid-api-mailer', array(&$this, 'settings_page'));
	}	

	public function settings_page()
	{ 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php esc_html($this->settings_title); ?></h1>	
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
		register_setting('mailer_settings', 'sendgrid_email_bcc', 'sanitize_items_per_line');
		register_setting('mailer_settings', 'sendgrid_name', 'sanitize_text_field');		


		//sendgrid settings
		register_setting('mailer_settings', 'sendgrid_web_api_key', 'sanitize_user');
		register_setting('mailer_settings', 'sendgrid_smtp_api_key', 'sanitize_text_field');
		register_setting('mailer_settings', 'sendgrid_smtp_username', 'sanitize_text_field');

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
			array(&$this, 'settings_input'), 
			'mailer_settings', 
			'mailer_settings_section',
			array('name' => 'sendgrid_email', 'type' => 'email') 
		);
		
		add_settings_field( 
			'sendgrid_email_bcc', 
			'Inbox Email (Bcc)', 
			array(&$this, 'settings_textarea'), 
			'mailer_settings', 
			'mailer_settings_section',
			array('name' => 'sendgrid_email_bcc') 
		);			

		add_settings_field( 
			'sendgrid_name', 
			'From Name', 
			array(&$this, 'settings_input'), 
			'mailer_settings', 
			'mailer_settings_section',
			array('name' => 'sendgrid_name') 
		);	

		add_settings_field( 
			'sendgrid_web_api_key', 
			'Web API Key', 
			array(&$this, 'settings_input'), 
			'mailer_settings', 
			'sendgrid_settings_section',
			array('name' => 'sendgrid_web_api_key') 
		);

		add_settings_field( 
			'sendgrid_smtp_api_key', 
			'SMTP API Key', 
			array(&$this, 'settings_input'), 
			'mailer_settings', 
			'sendgrid_settings_section',
			array('name' => 'sendgrid_smtp_api_key') 
		);	
		add_settings_field( 
			'sendgrid_smtp_username', 
			'SMTP Username', 
			array(&$this, 'settings_input'), 
			'mailer_settings', 
			'sendgrid_settings_section',
			array('name' => 'sendgrid_smtp_username') 
		);
		
	}
	
	public function settings_input($arr){
			$name = $arr['name'];
			$url = (array_key_exists('url', $arr)) ? '<a href="'.esc_url($arr['url']).'">?</a>' : null;
			$type = (array_key_exists('type', $arr)) ? $arr['type'] : 'text';
		?>
		<input type="<?php echo $type; ?>" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr(get_option($name)); ?>" /> <span><?php echo $url; ?></span>

	<?php }

	public function settings_textarea($arr){
		$name = $arr['name'];
		$url = (array_key_exists('url', $arr)) ? '<a href="'.esc_url($arr['url']).'">?</a>' : null;
	?>
		<span><?php echo $url; ?></span>
		<textarea rows="10" class="width-100" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>"><?php echo esc_textarea($this->sanitize_items_per_line(get_option($name))); ?></textarea>

	<?php }		


	public function add_bcc_to_phpmailer($phpmailer) {
		
		$bcc_email = get_option('new_admin_email');

		if (is_email($bcc_email)) {
			$phpmailer->addBCC($bcc_email);
		}
	}

	public function phpmailer($phpmailer)
	{
		if(!$this->is_enabled())
		{
			return $phpmailer;
		}

		$personalizations = array(
			array(
				"subject" => $phpmailer->Subject,
			)
		);
		
		$from = array(
			"email" => $this->email,
			"name"  => $this->name
		);
		
		// Get recipient addresses from wp_mail parameters
		$to = $phpmailer->getToAddresses();
		$cc = $phpmailer->getCcAddresses();
		$bcc = $phpmailer->getBccAddresses();
		
		// Populate the 'to' array
		foreach ($to as $address) {

			if(!array_key_exists('to', $personalizations[0]))
			{
				$personalizations[0]["to"] = array();
			}

			$personalizations[0]["to"][] = array("email" => $address[0]);
		}
		
		// Populate the 'cc' array
		foreach ($cc as $address) {

			if(!array_key_exists('cc', $personalizations[0]))
			{
				$personalizations[0]["cc"] = array();
			}

			$personalizations[0]["cc"][] = array("email" => $address[0]);
		}
		
		// Populate the 'bcc' array
		foreach ($bcc as $address) {

			if(!array_key_exists('bcc', $personalizations[0]))
			{
				$personalizations[0]["bcc"] = array();
			}

			$personalizations[0]["bcc"][] = array("email" => $address[0]);
		}
		
		// Additional emails from admin fields
		$config_to = $this->email_str_row_to_array($this->email_to);
		$config_cc = $this->email_str_row_to_array($this->email_cc);
		$config_bcc = $this->email_str_row_to_array($this->email_bcc);
		
		// Add additional 'to' emails
		foreach ($config_to as $email) {

			if(!array_key_exists('to', $personalizations[0]))
			{
				$personalizations[0]["to"] = array();
			}

			$personalizations[0]["to"][] = array("email" => $email);
		}
		
		// Add additional 'cc' emails
		foreach ($config_cc as $email) {

			if(!array_key_exists('cc', $personalizations[0]))
			{
				$personalizations[0]["cc"] = array();
			}
		
			$personalizations[0]["cc"][] = array("email" => $email);
		}
		
		// Add additional 'bcc' emails
		foreach ($config_bcc as $email) {

			if(!array_key_exists('bcc', $personalizations[0]))
			{
				$personalizations[0]["bcc"] = array();
			}

			$personalizations[0]["bcc"][] = array("email" => $email);
		}
		
		// Attachments
		$attachments = $phpmailer->getAttachments();
		$formatted_attachments = array();
		
		foreach ($attachments as $arr) {
			$pathname = $arr[0];
			$filename = $arr[2];
			$mimetype = $arr[4];
			$file = file_get_contents($pathname);
		
			$formatted_attachments[] = array(
				'content' => base64_encode($file),
				'type' => $mimetype,
				'filename' => $filename,
				'disposition' => 'attachment',
			);
		}
		

		// Build the payload
		$payload = array(
			'personalizations' => $personalizations,
			'from' => $from,
			'subject' => $phpmailer->Subject,
			'content' => array(
				array(
					'type' => 'text/html',
					'value' => $phpmailer->Body,
				)
			)
		);

		
		if(!empty($formatted_attachments))
		{
			$payload['attachments'] = $formatted_attachments;
		}

		// Set up the arguments for the request
		$args = array(
			'method'      => 'POST',
			'body'        => wp_json_encode($payload),
			'timeout'     => 45,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->web_api_key,
				'Content-Type'  => 'application/json',
			),
		);
		
		// Make the request
		$response = wp_remote_post($this->api_endpoint, $args);
		
		// Handle the response (optional)
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			write_log("SendGrid API request failed: $error_message");
		}
		

		//deletes attachemets and suppress exceptions writes
		$this->unlink_attachments();

	}


	public function unlink_attachments()
	{
		@array_map('unlink', glob(wp_upload_dir()['basedir'] . '/temp_*'));
	}

	public function has_attachments($attachments)
	{
		$output = false;
		
		if(is_array($attachments))
		{
			if(count($attachments) > 0)
			{	
				for($x = 0; $x < count($attachments); $x++)
				{
					if(array_key_exists('filename', $attachments[$x]) && array_key_exists('data', $attachments[$x]))
					{
						$output = true;
					}
				}
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
	public function from_name($name)
	{
		return ($this->name) ? $this->name : get_bloginfo('name');
	}

	public function from_email($email)
	{
		return ($this->email) ? $this->email : $email;
	}


	public function email_str_row_to_array($str)
	{
		$output = array();

		if($str)
		{
			$emails = explode(PHP_EOL, html_entity_decode($str));		
			$output = array_slice(array_unique(array_filter(array_map('sanitize_email', $emails))), 0, 10);
		}


		return $output;
	}

	public function sanitize_items_per_line($str)
	{
		$decoded_str = html_entity_decode($str);
	
		if ($decoded_str === false) {
			// Handle decoding error, perhaps log or throw an exception
			return false;
		}
	
		// Normalize line endings
		$emails = explode(PHP_EOL, $decoded_str);
	
		$unique_emails = array_unique(array_filter(array_map('sanitize_email', $emails)));
	
		// Limit the result to a configurable number
		$arr = array_slice($unique_emails, 0, $this->email_limit);
	
		return implode(PHP_EOL, $arr);
	}
	
}


?>