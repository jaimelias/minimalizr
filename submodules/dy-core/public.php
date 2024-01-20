<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Public {
    
    public function __construct()
    {
        $this->version = '0.0.6';
        $this->plugin_dir_url_file = plugin_dir_url( __FILE__ );
        $this->dirname_file = dirname( __FILE__ );
        add_shortcode('whatsapp', array(&$this, 'whatsapp_button'));
        add_action( 'wp_head', array(&$this, 'gtm_tracking_script'));
        add_action( 'minimal_pre_body', array(&$this, 'gtm_tracking_iframe'));
        add_action( 'wp_footer', array(&$this, 'whatsapp_modal'));
        add_action( 'wp_footer', array(&$this, 'picker_containers'));
        add_action( 'wp_head', array(&$this, 'gtag_tracking_script'));
        add_action( 'wp_head', array(&$this, 'facebook_pixel_tracking_script'));
        add_action('wp_head', array(&$this, 'whatsapp_modal_css'));
        add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array(&$this, 'enqueue_styles'));
        add_action('minimal_site_alert', array(&$this, 'site_alert'));
    }

    public function enqueue_scripts()
    {
        global $dy_load_recaptcha_scripts;
        global $dy_load_picker_scripts;
        global $dy_load_request_form_utilities_scripts;

        $sentry_api_key = get_option('dy_sentry_api_key');

        if(!empty($sentry_api_key))
        {
            wp_enqueue_script('sentry-lazy-load', 'https://js.sentry-cdn.com/'.esc_html($sentry_api_key).'.min.js', array(), '', false);
            wp_add_inline_script('sentry-lazy-load', $this->sentry(), 'after');
        }
        
        wp_enqueue_script('landing-cookies', $this->plugin_dir_url_file . 'js/cookies.js', array('jquery'), $this->version, true);
        wp_add_inline_script('landing-cookies', $this->cookies(), 'before');

        wp_enqueue_script('sha512', $this->plugin_dir_url_file . 'js/sha512.js', '', 'async_defer', true);

        wp_enqueue_script('dy-qrcode', $this->plugin_dir_url_file . 'js/qrcode.min.js', array('jquery'), time(), true);

        wp_enqueue_script('dy-core-utilities', $this->plugin_dir_url_file . 'js/utilities.js', array('sha512', 'jquery', 'landing-cookies'), $this->version, true);
        wp_add_inline_script('dy-core-utilities', $this->args(), 'before');
        

        if(isset($dy_load_recaptcha_scripts))
        {
            wp_enqueue_script('recaptcha-v3', 'https://www.google.com/recaptcha/api.js', '', 'async_defer', true);
        }

        //picker start

        if(isset($dy_load_picker_scripts))
        {
            load_picker_scripts($this->plugin_dir_url_file, $this->dirname_file);
        }


        
        if(isset($dy_load_request_form_utilities_scripts))
        {
            wp_enqueue_script('dy-core-request-form-utilities', $this->plugin_dir_url_file . 'js/request-form-utilities.js', array('jquery', 'landing-cookies'), $this->version, false);
        }
    }


    public function enqueue_styles()
    {
        global $dy_load_picker_scripts;

        if(isset($dy_load_picker_scripts))
        {
            load_picker_styles($this->plugin_dir_url_file);
        }

        
    }

    public function cookies()
    {
        $visit_cookies = array('device', 'landing_domain', 'landing_path', 'channel');
        $google_ads_cookies = array('utm_source', 'utm_medium', 'utm_campaign', 'gclid');

        return 'const visitCookies = '.json_encode($visit_cookies).'; const googleAdsCookies = '.json_encode($google_ads_cookies).';';
    }

    public function sentry()
    {
        return 'Sentry.onLoad((function(){Sentry.init({tracesSampleRate: 1.0})}));';
    }
    
    public function args()
    {
        global $post;

        $args = array(
            'homeUrl' => home_url(),
            'permalink' => get_the_permalink(),
            'pluginUrl' => $this->plugin_dir_url_file,
            'lang' => current_language(),
            'ipGeoLocation' => array(
                'token' => get_option('dy_ipgeolocation_api_token')
            )
        );

        if(isset($post))
        {
            $args['post_id'] = $post->ID;
            $args['post_title'] = $post->post_title;
        }
        
        return 'const dyCoreArgs = '.json_encode($args).';';
    }

    public function gtm_tracking_script()
    {
        $value = get_option('dy_gtm_tracking_id');

        if(!empty($value)): ?>

        <!-- Start Google - Global Tag Manager (GMT) -->
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_html($value); ?>');
        </script>
        <!-- End Google - Global Tag Manager (GMT) -->

        <?php endif;
    }

    public function gtm_tracking_iframe()
    {
        $value = get_option('dy_gtm_tracking_id');

        if(!empty($value)): ?>

        <!-- Start Google - Global Tag Manager (GMT) noscript-->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_html($value); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google - Global Tag Manager (GMT) noscript -->

        <?php endif;
    }

    public function gtag_tracking_script()
    {
        $analytics = get_option('dy_gtag_tracking_id');

        if(!empty($analytics)): ?>

        <!-- Start Google - Analytics GA4 (GTAG) -->

        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_html($analytics); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo esc_html($analytics); ?>');
        </script>
        
        <!-- End Google - Analytics GA4 (GTAG) -->

        <?php endif;       
    }

    public function facebook_pixel_tracking_script()
    {
        $value = get_option('dy_facebook_pixel_id');

        if(!empty($value)): ?>

        <!-- Start Facebook Pixel -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window,document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '<?php echo esc_html($value); ?>'); 
            fbq('track', 'PageView');
		</script>
        <!-- End Facebook Pixel -->

        <?php endif;           
    }


	public function whatsapp_button($content = '')
	{
		return whatsapp_button();
	}

    public function site_alert()
    {

        $languages = get_languages();
        $current_language = current_language();
        $default_language = default_language();
        $output = '';

        for($x = 0; $x < count($languages); $x++)
        {
			$lang = $languages[$x];

            if($lang === $current_language)
            {
                $prefix = ($default_language === $lang) ? '' : '_'.$lang;
                $notification_raw = html_entity_decode(get_option('dy_site_alert'.$prefix));

                if(!empty($notification_raw))
                {
                    $output = '<div class="dy-site-alert"><div class="dy-site-alert-content"><span class="dashicons dashicons-warning"></span> ' . $notification_raw . '</div></div>';
                }
            }
        }

        echo $output;

    }

    public function picker_containers()
    {
        ?>
            <div id="datepicker-container"></div>
            <div id="timepicker-container"></div>
        <?php
    }

    public function whatsapp_modal()
    {
        ?>

            <div id="dy-whatsapp-modal" class="hidden">
                <div id="dy-whatsapp-modal-content">
                    <span id="dy-whatsapp-modal-close">&times;</span>
                    <div id="dy-whatsapp-qrcode"></div>
                    <div id="dy-whatsapp-link" class="pure-button small"><a href="#"><?php echo esc_html__('Open Web Whatsapp', 'dynamicpackages'); ?></a></div>
                </div>
            </div>

        <?php
    }

    public function whatsapp_modal_css()
    {
        ?>
        <style type="text/css" id="whatsapp-modal-css">

            .hidden{
                display: none;
            }
            #dy-whatsapp-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 300px;
                height: 300px;
                margin: 0;
            }

            #dy-whatsapp-modal-content {
                width: 100%;
                height: 100%;
                background-color: #dcf8c6;
                border-radius: 5px;
                text-align: center;
                box-shadow: 0 0 0 1px rgba(7,94,84, 0.2);
                position: relative;
            }

            #dy-whatsapp-modal-content img{

                margin: 0 auto;
                top: 20px;
                position: relative;
                display: block;

            }

            #dy-whatsapp-link{
                position: absolute;
                bottom: 10px;
                left: 50%;
                transform: translate(-50%, -50%);
                box-shadow: 0 0 1px rgba(0,0,0,0.3) !important;
                display: inline-block !important;
                border-radius: 25px !important;
                background-color: #128c7e !important;

            }
            #dy-whatsapp-link a
            {
                color: #fff !important;
                text-decoration: none;
            }

            #dy-whatsapp-modal-close {
                position: absolute;
                text-align: center;
                top: -10px;
                right: -10px;
                width: 30px;
                height: 30px;
                font-size: 20px;
                cursor: pointer;
                color: #fff;
                background-color: #075e54;
            }

        </style>
        <?php
    }

}

?>