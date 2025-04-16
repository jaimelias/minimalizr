<?php

if ( !defined( 'WPINC' ) ) exit;

$plugin_dir_path = plugin_dir_path( __FILE__ );

if(!defined('DY_CORE_FUNCTIONS'))
{
    require_once $plugin_dir_path . 'functions.php';
}

if(!class_exists('Dy_Mailer'))
{
    require_once $plugin_dir_path . 'mailer.php';
    new Dy_Mailer();
}

if(!class_exists('Dynamic_Core_Admin'))
{
    require_once $plugin_dir_path . 'admin.php';
    new Dynamic_Core_Admin();
}

if(!class_exists('Dynamic_Core_Public'))
{
    require_once $plugin_dir_path . 'public.php';
    new Dynamic_Core_Public();
}

if(!class_exists('Dynamic_Core_WP_JSON'))
{
    require_once $plugin_dir_path . 'wp-json.php';
    new Dynamic_Core_WP_JSON();
}

if(!class_exists('Dynamic_Core_Providers'))
{
    require_once $plugin_dir_path . 'integrations/providers/providers.php';
    new Dynamic_Core_Providers();
}
if(!class_exists('Dynamic_Core_Orders'))
{
    //require_once $plugin_dir_path . 'integrations/orders/orders.php';
    //new Dynamic_Core_Orders();
}



?>