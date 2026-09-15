<?php

if ( !defined( 'WPINC' )) exit;

if(!class_exists('Dy_Core_Init'))
{
    define('DY_CORE_VERSION', '1.1.37');

    if ( !defined( 'DY_CORE_TEXTDOMAIN' ) ) {
        define( 'DY_CORE_TEXTDOMAIN', 'dycore' );
    }

    #[AllowDynamicProperties]
    class Dy_Core_Init {

        public function __construct()
        {

            add_action('wp_loaded', 'dy_core_maybe_flush_rewrite_rules', PHP_INT_MAX);

            $this->load_dependencies();

            add_action( 'init', [$this, 'load_textdomain'], 1 );

            $version = is_local_host() ? time() : DY_CORE_VERSION;

            new Dy_WAF();
            new dy_errors();
            new DY_SendGrid();
            new Dynamic_Sitemap();
            new Dynamic_Core_Admin($version);
            new Dynamic_Core_Public($version);  
            new Dynamic_Core_WP_JSON();
            new Dynamic_Core_Providers();
            new Dy_Confirmation_Page();
        }

        public function load_textdomain(): void
        {
            load_plugin_textdomain(
                DY_CORE_TEXTDOMAIN,
                false,
                dirname( plugin_basename( __FILE__ ) ) . '/languages'
            );
        }

        public function load_dependencies()
        {
            $plugin_dir_path = plugin_dir_path( __FILE__ );

            //core helpers
            require_once $plugin_dir_path . 'functions.php';
            
            require_once $plugin_dir_path . 'errors-page.php';
            require_once $plugin_dir_path . 'security/queries.php';
            require_once $plugin_dir_path . 'security/write_log.php';
            require_once $plugin_dir_path . 'security/get-option.php';
            require_once $plugin_dir_path . 'security/ip-utilities.php';
            require_once $plugin_dir_path . 'security/server.php';
            require_once $plugin_dir_path . 'security/waf.php';
            require_once $plugin_dir_path . 'controllers/abstracts/input_abstract.php';
            require_once $plugin_dir_path . 'controllers/abstracts/select_abstract.php';
            require_once $plugin_dir_path . 'controllers/abstracts/textarea_abstract.php';
            require_once $plugin_dir_path . 'controllers/controller_utilities.php';
            require_once $plugin_dir_path . 'controllers/extended_option.php';
            require_once $plugin_dir_path . 'controllers/extended_term_meta.php';

            //third-party integrations
            require_once $plugin_dir_path . 'integrations/gtag.php';
            require_once $plugin_dir_path . 'integrations/sendgrid.php';
            require_once $plugin_dir_path . 'integrations/cloudflare-ban.php';
            require_once $plugin_dir_path . 'integrations/cloudflare-browser-run.php';
            require_once $plugin_dir_path . 'integrations/cloudflare-turnstile.php';
            require_once $plugin_dir_path . 'integrations/sitemap.php';
            require_once $plugin_dir_path . 'integrations/handsontable.php';
            require_once $plugin_dir_path . 'integrations/parsedown.php';

            //ai training data
            require_once $plugin_dir_path . 'training-data/concatenate_object_to_text.php';
            require_once $plugin_dir_path . 'training-data/concatenate_object_to_html.php';
            
            //core endpoints
            require_once $plugin_dir_path . 'public.php';
            require_once $plugin_dir_path . 'wp-json.php';
            require_once $plugin_dir_path . 'admin.php';

            //e-commerce
            require_once $plugin_dir_path . 'e-commerce/transactions.php';
            require_once $plugin_dir_path . 'e-commerce/providers.php';
            require_once $plugin_dir_path . 'e-commerce/confirmation-page.php';
        }
    }

    new Dy_Core_Init();
}


?>
