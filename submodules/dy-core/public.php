<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Public {
    
    public function __construct()
    {
        $this->version = '0.1.105';
        $this->plugin_dir_url_file = plugin_dir_url( __FILE__ );
        $this->dirname_file = dirname( __FILE__ );

		if(is_in_theme())
		{
			$this->plugin_dir_url_file = get_stylesheet_directory_uri().'/submodules/dy-core/';
			$this->plugin_dir = get_template_directory().'/submodules/dy-core/';
		}

        add_shortcode('whatsapp', array($this, 'whatsapp_button'));
        add_action( 'wp_footer', array($this, 'whatsapp_modal'));
        add_action( 'wp_footer', array($this, 'picker_containers'));
        add_action( 'wp_head', array($this, 'gtag_tracking_script'));
        add_action( 'wp_footer', array($this, 'gtag_conversion_events_script'), PHP_INT_MAX);

        add_action( 'wp_head', array($this, 'facebook_pixel_tracking_script'));
        add_action('wp_head', array($this, 'whatsapp_modal_css'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('minimal_site_alert', array($this, 'site_alert'));
        add_action('minimal_footer_alert', array($this, 'footer_alert'));

        add_filter('wp_resource_hints', array($this, 'resource_hints'), 10, 2);
    }

    public function resource_hints($urls, $relation_type)
    {
        global $dy_load_turnstile_scripts;

        if(
            $relation_type === 'preconnect'
            && isset($dy_load_turnstile_scripts)
        )
        {
            $urls[] = 'https://challenges.cloudflare.com';
        }

        return $urls;
    }

    public function enqueue_scripts()
    {
        global $dy_load_turnstile_scripts;
        global $dy_load_picker_scripts;
        global $dy_load_request_form_utilities_scripts;

        $sentry_api_key = get_option('dy_sentry_api_key');

        if(!empty($sentry_api_key))
        {
            wp_enqueue_script('sentry-lazy-load', 'https://js.sentry-cdn.com/'.esc_html($sentry_api_key).'.min.js', array(), '', false);
            wp_add_inline_script('sentry-lazy-load', $this->sentry(), 'after');
        }

       if(isset($dy_load_turnstile_scripts))
        {
            wp_enqueue_script(
                'turnstile-compat',
                'https://challenges.cloudflare.com/turnstile/v0/api.js',
                array(),
                'async_defer',
                false
            );
        }
        
        wp_enqueue_script('landing-cookies', $this->plugin_dir_url_file . 'js/cookies.js', array('jquery'), $this->version, true);
        wp_add_inline_script('landing-cookies', $this->cookies(), 'before');

        wp_enqueue_script('dy-qrcode', $this->plugin_dir_url_file . 'js/qrcode.min.js', array('jquery'), 'async_defer', true);


 

        wp_enqueue_script('dy-core-utilities', $this->plugin_dir_url_file . 'js/utilities.js', array('jquery', 'landing-cookies'), $this->version, true);
        wp_add_inline_script('dy-core-utilities', $this->args(), 'before');
        

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

        $site_time = get_site_time();

        $args = array(
            'homeUrl' => home_url(),
            'permalink' => get_the_permalink(),
            'wpJsonUrl' => rest_url('dy-core'),
            'lang' => current_language(),
            'whatsappNumber' => whatsapp_number()
        );

        foreach($site_time as $k => $v)
        {
            $args[$k] = $v;
        }


        if($post instanceof WP_Post)
        {
            $args['post_id'] = $post->ID;
            $args['post_title'] = $post->post_title;
        }

        $analytics = strtoupper(
            trim((string) get_option('dy_gtag_tracking_id'))
        );

        if(1 === preg_match('/^G-[A-Z0-9]+$/', $analytics))
        {
            $args['google_analytics_id'] = $analytics;
        }
        
        return 'const dyCoreArgs = '.json_encode($args).';';
    }


    public function gtag_tracking_script()
    {
        $analytics = strtoupper(
            trim((string) get_option('dy_gtag_tracking_id'))
        );

        $google_ads_id = strtoupper(
            trim((string) get_option('dy_google_ads_id'))
        );

        if (1 !== preg_match('/^(AW|G|GT|GTM)-[0-9A-Z]+$/', $analytics))
        {
            $analytics = '';
        }

        if (1 !== preg_match('/^AW-[0-9A-Z]+$/', $google_ads_id))
        {
            $google_ads_id = '';
        }

        $loader_id = !empty($analytics)
            ? $analytics
            : $google_ads_id;

        if(empty($loader_id))
        {
            return;
        }

        $gtag_src = add_query_arg(
            array('id' => $loader_id),
            'https://www.googletagmanager.com/gtag/js'
        );

        ?>

        <!-- Start Google tag (gtag.js) -->
        <script async src="<?php echo esc_url($gtag_src); ?>"></script>
        <script>
            
            window.dataLayer = window.dataLayer || [];

            window.gtag = window.gtag || function() {
                window.dataLayer.push(arguments);
            };

            window.gtag('js', new Date());

            <?php if(!empty($analytics)): ?>
            window.gtag(
                'config',
                <?php echo wp_json_encode($analytics); ?>
            );
            <?php endif; ?>

            <?php if(!empty($google_ads_id)): ?>
            window.gtag(
                'config',
                <?php echo wp_json_encode($google_ads_id); ?>
            );
            <?php endif; ?>
        </script>
        <!-- End Google tag (gtag.js) -->

        <?php
    }

    public function gtag_conversion_events_script()
    {
        $events = isset($GLOBALS['dy_gtag_server_events'])
            && is_array($GLOBALS['dy_gtag_server_events'])
                ? array_values($GLOBALS['dy_gtag_server_events'])
                : array();

        if(empty($events))
        {
            return;
        }

        $analytics = strtoupper(
            trim((string) get_option('dy_gtag_tracking_id'))
        );

        $google_ads_id = strtoupper(
            trim((string) get_option('dy_google_ads_id'))
        );

        if(1 !== preg_match('/^G-[A-Z0-9]+$/', $analytics))
        {
            $analytics = '';
        }

        if(1 !== preg_match('/^AW-[0-9]+$/', $google_ads_id))
        {
            $google_ads_id = '';
        }

        $ads_labels = array(
            'purchase' => trim(
                (string) get_option('dy_google_ads_purchase_label')
            ),
            'generate_lead' => trim(
                (string) get_option('dy_google_ads_lead_label')
            )
        );

        $commands = array();

        foreach($events as $event)
        {
            if(
                empty($event['name'])
                || empty($event['params'])
                || !is_array($event['params'])
            )
            {
                continue;
            }

            /*
            * Evento recomendado de GA4. El destino explícito evita
            * que la conversión de Ads reciba también este evento.
            */
            if(!empty($analytics))
            {
                $ga4_params = $event['params'];
                $ga4_params['send_to'] = $analytics;

                $commands[] = array(
                    'event' => $event['name'],
                    'params' => $ga4_params
                );
            }

            /*
            * Conversión nativa de Google Ads.
            */
            $label = isset($ads_labels[$event['name']])
                ? $ads_labels[$event['name']]
                : '';

            if(
                !empty($google_ads_id)
                && 1 === preg_match('/^[A-Za-z0-9_-]+$/', $label)
            )
            {
                $commands[] = array(
                    'event' => 'conversion',
                    'params' => array(
                        'send_to' => $google_ads_id . '/' . $label,
                        'value' => $event['params']['value'],
                        'currency' => $event['params']['currency'],
                        'transaction_id' => $event['params']['transaction_id']
                    )
                );
            }
        }

        if(empty($commands))
        {
            return;
        }

        $commands_json = wp_json_encode(
            $commands,
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );
        ?>
        <script id="dy-gtag-confirmed-conversions">
            (function(commands) {
                if(typeof window.gtag !== 'function') {
                    return;
                }

                commands.forEach(function(command) {
                    window.gtag(
                        'event',
                        command.event,
                        command.params
                    );
                });
            })(<?php echo $commands_json; ?>);
        </script>
        <?php
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
    public function site_alert() {
        echo $this->render_alert('site_alert');
    }

    public function footer_alert() {
        echo $this->render_alert('footer_alert');
    }

    public function render_alert($alert_id = '')
    {
        static $prefix = null;

        if ($prefix === null) {
            $current_language = current_language();
            $default_language = default_language();
            $prefix = ($current_language === $default_language) ? '' : '_' . $current_language;
        }

        $notification_raw = html_entity_decode(get_option('dy_' . $alert_id . $prefix));

        if (empty($notification_raw)) {
            return '';
        }

        $parsed_notification = do_shortcode($notification_raw);

        return sprintf(
            '<div class="dy-%1$s"><div class="dy-%1$s-content">%2$s</div></div>',
            esc_attr(str_replace('_', '-', $alert_id)),
            $parsed_notification
        );
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
                    <div id="dy-whatsapp-link" class="pure-button small"><a href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Web Whatsapp'); ?></a></div>
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