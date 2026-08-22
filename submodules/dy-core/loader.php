<?php

if ( !defined( 'WPINC' )) exit;

if(!class_exists('Dy_Core_Init'))
{
    #[AllowDynamicProperties]
    class Dy_Core_Init {

        public function __construct()
        {
            $this->plugin_dir_path = plugin_dir_path( __FILE__ );
            $this->load_dependencies();
            $this->init();
        }
        public function load_dependencies()
        {
            //core helpers
            require_once $this->plugin_dir_path . 'functions.php';
            require_once $this->plugin_dir_path . 'security/waf.php';
            require_once $this->plugin_dir_path . 'security/queries.php';
            require_once $this->plugin_dir_path . 'controllers/input_controller.php';
            require_once $this->plugin_dir_path . 'controllers/select_controller.php';
            require_once $this->plugin_dir_path . 'textarea/select_controller.php';

            //third-party integrations
            require_once $this->plugin_dir_path . 'integrations/gtag.php';
            require_once $this->plugin_dir_path . 'integrations/mailer.php';
            require_once $this->plugin_dir_path . 'integrations/cloudflare.php';
            require_once $this->plugin_dir_path . 'integrations/sitemap.php';

            //ai training data
            require_once $this->plugin_dir_path . 'training-data/concatenate_object_to_text.php';
            require_once $this->plugin_dir_path . 'training-data/concatenate_object_to_html.php';
            
            //core endpoints
            require_once $this->plugin_dir_path . 'public.php';
            require_once $this->plugin_dir_path . 'wp-json.php';
            require_once $this->plugin_dir_path . 'admin.php';

            //e-commerce
            require_once $this->plugin_dir_path . 'e_commerce/providers/providers.php';
            //require_once $this->plugin_dir_path . 'e_commerce/orders/orders.php';
        }
        public function init()
        {
            new Dy_WAF();
            new Dy_Mailer();
            new Dynamic_Sitemap();
            new Dynamic_Core_Admin();
            new Dynamic_Core_Public();
            new Dynamic_Core_WP_JSON();
            new Dynamic_Core_Providers();
            //new Dynamic_Core_Orders();
        }
    }

    new Dy_Core_Init();
}


?>