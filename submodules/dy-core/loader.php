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
            require_once $this->plugin_dir_path . 'functions.php';
            require_once $this->plugin_dir_path . 'mailer.php';
            require_once $this->plugin_dir_path . 'admin.php';
            require_once $this->plugin_dir_path . 'public.php';
            require_once $this->plugin_dir_path . 'wp-json.php';
            require_once $this->plugin_dir_path . 'integrations/providers/providers.php';
            //require_once $this->plugin_dir_path . 'integrations/orders/orders.php';
        }
        public function init()
        {
            new Dy_Mailer();
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