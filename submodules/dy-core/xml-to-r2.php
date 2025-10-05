<?php
/**
 * Plugin Name: Export XML Backup to Cloudflare R2
 * Description: Export the WordPress content (WXR XML) via a secured REST API endpoint and upload to Cloudflare R2.
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) exit;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class EXPORT_XML_BACKUP_TO_CLOUDFLARE_R2 {

  /** @var string */
  public $plugin_name = 'Export XML Backup to Cloudflare R2';

  /** @var string */
  public $slug = 'xml-to-r2';

  /** @var string */
  public $setting_id;

  /** @var string */
  public $section_credentials;

  public function __construct() {

    if (PHP_MAJOR_VERSION < 8) return;

    $this->setting_id = 'xml_to_r2';
    $this->section_credentials = $this->setting_id . '-section-r2-credentials';

    add_action('admin_menu', array($this, 'admin_menu'));
    add_action('admin_init', array($this, 'settings_init'));
    add_action('rest_api_init', array($this, 'register_routes'));
  }

  public function register_routes() {
    register_rest_route('dy-core', '/xml-to-r2', [
      'methods'  => 'GET',
      'callback' => array($this, 'handle_export'),
      'permission_callback' => function () {
        return secure_get('token', '') === get_option('cf_r2_wp_json_token', '');
      },
      'args' => [
        'gzip' => [
          'description' => 'If set to 1, returns a gzip-compressed XML.',
          'type'        => 'boolean',
          'required'    => false,
        ],
      ],
    ]);
  }

  // ===== Admin Settings =====

  public function settings_init() {
    add_settings_section(
      $this->section_credentials,
      esc_html__('Cloudflare R2 Credentials', 'xml-to-r2'),
      function () {
        echo '<p>' . esc_html__('Provide your R2 account details. PHP version >= 8 is required.', 'xml-to-r2') . '</p>';
      },
      $this->setting_id
    );

    register_setting($this->setting_id, 'cf_r2_wp_json_token', 'sanitize_user');
    register_setting($this->setting_id, 'cf_r2_account_id', 'sanitize_user');
    register_setting($this->setting_id, 'cf_r2_access_key_id', 'sanitize_user');
    register_setting($this->setting_id, 'cf_r2_secret_access_key', 'sanitize_user');
    register_setting($this->setting_id, 'cf_r2_xml_backup_bucket', 'sanitize_user');

    add_settings_field(
      'cf_r2_wp_json_token',
      esc_html__('WP-JSON Token', 'xml-to-r2'),
      array($this, 'settings_input'),
      $this->setting_id,
      $this->section_credentials,
      array('name' => 'cf_r2_wp_json_token', 'type' => 'text')
    );

    add_settings_field(
      'cf_r2_account_id',
      esc_html__('Cloudflare Account ID', 'xml-to-r2'),
      array($this, 'settings_input'),
      $this->setting_id,
      $this->section_credentials,
      array('name' => 'cf_r2_account_id', 'type' => 'text', 'url' => 'https://dash.cloudflare.com/')
    );

    add_settings_field(
      'cf_r2_access_key_id',
      esc_html__('R2 Access Key ID', 'xml-to-r2'),
      array($this, 'settings_input'),
      $this->setting_id,
      $this->section_credentials,
      array('name' => 'cf_r2_access_key_id', 'type' => 'text')
    );

    add_settings_field(
      'cf_r2_secret_access_key',
      esc_html__('R2 Secret Access Key', 'xml-to-r2'),
      array($this, 'settings_input'),
      $this->setting_id,
      $this->section_credentials,
      array('name' => 'cf_r2_secret_access_key', 'type' => 'text')
    );

    add_settings_field(
      'cf_r2_xml_backup_bucket',
      esc_html__('R2 Backup Bucket', 'xml-to-r2'),
      array($this, 'settings_input'),
      $this->setting_id,
      $this->section_credentials,
      array('name' => 'cf_r2_xml_backup_bucket', 'type' => 'text')
    );
  }

  public function sanitize_secret($value) {
    $value = is_string($value) ? trim(wp_kses_post($value)) : $value;
    return $value;
  }

  public function settings_input($arr){
    $name  = $arr['name'];
    $url   = (array_key_exists('url', $arr)) ? '<a target="_blank" rel="noopener noreferrer" href="'.esc_url($arr['url']).'">?</a>' : '';
    $type  = (array_key_exists('type', $arr)) ? $arr['type'] : 'text';
    $value = ($type === 'checkbox') ? 1 : get_option($name);
    ?>
      <input
        type="<?php echo esc_attr($type); ?>"
        name="<?php echo esc_attr($name); ?>"
        id="<?php echo esc_attr($name); ?>"
        value="<?php echo esc_attr($value); ?>"
        <?php echo ($type === 'checkbox') ? checked( 1, get_option($name), false ) : ''; ?>
      /> <span><?php echo $url; ?></span>
    <?php
  }

  public function admin_menu() {
    add_menu_page(
      $this->plugin_name,
      $this->plugin_name,
      'manage_options',
      $this->slug,
      array($this, 'settings_page'),
      'dashicons-database'
    );
  }

  public function settings_page() { ?>
    <div class="wrap">
      <form action="options.php" method="post">
        <h1><?php echo esc_html($this->plugin_name); ?></h1>
        <?php
          settings_fields($this->setting_id);
          do_settings_sections($this->setting_id);
          submit_button();
        ?>
      </form>
    </div>
  <?php }

  // ===== Export Logic (WXR XML) =====

  public function handle_export(\WP_REST_Request $req) {
    if (function_exists('set_time_limit')) @set_time_limit(0);
    if (function_exists('wp_raise_memory_limit')) @wp_raise_memory_limit('admin');
    ignore_user_abort(true);

    $gzip = (bool) $req->get_param('gzip');

    // Load WP core exporter
    if (!function_exists('export_wp')) {
      $exporter = ABSPATH . 'wp-admin/includes/export.php';
      if (!file_exists($exporter)) {
        return new \WP_REST_Response(['error' => 'WordPress exporter not found.'], 500);
      }
      require_once $exporter;
    }

    // Build filename
    $host  = parse_url(home_url(), PHP_URL_HOST);
    $site  = preg_replace('~[^a-z0-9\-]+~i', '-', (string)$host);
    $fname = sprintf('%s-%s.xml', $site, gmdate('Ymd-His'));
    $downloadName = $gzip ? ($fname . '.gz') : $fname;

    // Capture the WXR output from export_wp() into memory
    // We export ALL content; no date/author/category filtering; all statuses
    $args = [
      'content'   => 'all',
      'download'  => false, // we'll manage headers/streaming
      // Other accepted args in core exporter (kept false to include all):
      'author'    => false,
      'category'  => false,
      'start_date'=> false,
      'end_date'  => false,
      'status'    => false,
    ];

    ob_start();
    try {
      export_wp($args); // echoes XML
      $xml = ob_get_clean();
    } catch (\Throwable $e) {
      ob_end_clean();
      return new \WP_REST_Response(['error' => 'Export failed: ' . $e->getMessage()], 500);
    }

    if (!is_string($xml) || $xml === '') {
      return new \WP_REST_Response(['error' => 'Empty export output.'], 500);
    }

    // Create temp file and write XML (optionally gzip)
    if (!function_exists('wp_tempnam')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $tmpPath = wp_tempnam($downloadName);
    if ($gzip) $tmpPath .= '.gz';

    $okWrite = false;
    if ($gzip && function_exists('gzopen')) {
      if ($h = gzopen($tmpPath, 'wb')) {
        gzwrite($h, $xml);
        gzclose($h);
        $okWrite = true;
      }
    } else {
      $okWrite = (file_put_contents($tmpPath, $xml) !== false);
    }
    unset($xml); // free memory

    if (!$okWrite) {
      return new \WP_REST_Response(['error' => 'Unable to write temporary export file.'], 500);
    }

    // Attempt R2 upload (best-effort; we still return the file even if upload fails)
    $upload_status = 'skipped';
    try {
      $ok = $this->upload_to_r2($tmpPath, $downloadName);
      $upload_status = $ok ? 'ok' : 'skipped';
    } catch (\Throwable $e) {
      $upload_status = 'error';
      if (function_exists('error_log')) error_log('[EXPORT_XML_BACKUP_TO_CLOUDFLARE_R2] Upload error: ' . $e->getMessage());
    }

    // Send headers + stream file to client
    nocache_headers();
    header('Content-Type: ' . ($gzip ? 'application/gzip' : 'application/xml; charset=UTF-8'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('X-Backup-R2-Upload: ' . $upload_status);

    if ($fh = fopen($tmpPath, 'rb')) {
      while (!feof($fh)) {
        echo fread($fh, 8192);
        @flush();
      }
      fclose($fh);
    }
    @unlink($tmpPath);

    exit;
  }

  /**
   * Upload a file to Cloudflare R2 using aws/aws-sdk-php S3Client.
   *
   * @param string $localPath
   * @param string $remoteKey
   * @return bool true on success, false if credentials/bucket incomplete
   * @throws AwsException on hard failure
   */
  private function upload_to_r2(string $localPath, string $remoteKey) : bool {
    $accountId = trim((string) get_option('cf_r2_account_id'));
    $accessKey = trim((string) get_option('cf_r2_access_key_id'));
    $secretKey = trim((string) get_option('cf_r2_secret_access_key'));
    $bucket    = trim((string) get_option('cf_r2_xml_backup_bucket'));

    if ($accountId === '' || $accessKey === '' || $secretKey === '' || $bucket === '') {
      return false;
    }

    $remoteKey = ltrim($remoteKey, '/');

    $endpointPath = sprintf('https://%s.r2.cloudflarestorage.com', $accountId);

    $mkClient = function($usePathStyle) use ($endpointPath, $accessKey, $secretKey) {
      return new \Aws\S3\S3Client([
        'version'                 => 'latest',
        'region'                  => 'auto',
        'endpoint'                => $endpointPath,
        'use_path_style_endpoint' => (bool)$usePathStyle,
        'signature_version'       => 'v4',
        'credentials'             => ['key' => $accessKey, 'secret' => $secretKey],
        'http' => ['connect_timeout' => 10, 'timeout' => 0],
      ]);
    };

    $ctype = (substr($remoteKey, -3) === '.gz') ? 'application/gzip' : 'application/xml';

    $tryPut = function(\Aws\S3\S3Client $client) use ($bucket, $remoteKey, $ctype, $localPath) {
      $client->putObject([
        'Bucket'      => $bucket,
        'Key'         => $remoteKey,
        'Body'        => fopen($localPath, 'rb'),
        'ContentType' => $ctype,
        'Metadata'    => ['site' => (string)home_url(), 'when' => gmdate('c')],
      ]);
    };

    try {
      $tryPut($mkClient(true));   // path-style first (CF recommended)
      return true;
    } catch (\Aws\Exception\AwsException $e1) {
      if (function_exists('error_log')) error_log('[EXPORT_XML_BACKUP_TO_CLOUDFLARE_R2] path-style PutObject: ' . ($e1->getAwsErrorMessage() ?: $e1->getMessage()));
      try {
        $tryPut($mkClient(false)); // fallback to virtual-hosted style
        return true;
      } catch (\Aws\Exception\AwsException $e2) {
        if (function_exists('error_log')) error_log('[EXPORT_XML_BACKUP_TO_CLOUDFLARE_R2] vhost-style PutObject: ' . ($e2->getAwsErrorMessage() ?: $e2->getMessage()));
        throw $e2;
      }
    }
  }

}