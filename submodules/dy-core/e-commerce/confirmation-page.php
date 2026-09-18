<?php

declare(strict_types=1);

class Dy_Confirmation_Page
{
    private $version;

    private static mixed $tx = null;

    /** @var array<string, bool> */
    private static array $cache = [];

    private static int $http_status = 400;

    private static string $status = 'error';

    /**
     * Registra los hooks necesarios para manejar el endpoint de transacciones.
     */
    public function __construct(string|int $version){

        $priority = 10;
        $this->version = $version;

        add_action('init', [$this, 'add_rewrite_rule'], $priority);

        add_filter('query_vars', [$this, 'register_custom_query_var'], $priority);


        //regenerate $post from $tx->dy_id
        add_action('pre_get_posts', [$this, 'regenerate_post_object'], PHP_INT_MIN);
        add_action('wp', [$this, 'analytics'], $priority);

        /* BELLOW THIS LINE NO CALLBACK CAN BE TRIGGERED USING "INIT" OR "ANY HOOK BEFORE INIT" */

        //converts the regenerated post into a page
        add_action('pre_get_posts', [$this, 'main_wp_query'], $priority);

        //loads the page.php template
        add_filter('template_include', [$this, 'locate_template'], $priority );


        //template parts
        add_action('wp_head', [$this, 'meta_tags'] , $priority);
        add_filter('the_content', [$this, 'the_content'], $priority);
        add_filter('pre_get_document_title', [$this, 'wp_title'], $priority);
        add_filter('the_title', [$this, 'the_title'], $priority);
        add_filter('get_the_excerpt', [$this, 'get_the_excerpt'], $priority);
        add_action('template_redirect', [$this, 'template_redirect'], $priority);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], $priority);
    }

	public function main_wp_query($query)
	{
		if(isset($query->query_vars[DY_CORE_CONFIRMATION_PAGE_SLUG]) && $query->is_main_query())
		{
			$query->set('post_type', 'page');
			$query->set( 'posts_per_page', 1);
		}
	}

	public function locate_template($template)
	{
		if(get_query_var(DY_CORE_CONFIRMATION_PAGE_SLUG))
		{
			$new_template = locate_template( [ 'page.php' ] );
			return $new_template;			
		}
		return $template;
	}

    /**
     * Determina si la request actual corresponde al endpoint /dy-tx/{tx_id}.
     */
    public static function is_confirmation_page(): bool
    {    
        $cache_key = 'is_confirmation_page';

        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

        if (!did_action('pre_get_posts')) {

            $level = 'called_too_early';
            $message = sprintf('%s() was called before pre_get_posts.', __METHOD__);

            write_log($message, true, true, $level);

            wp_die(
                esc_html($message),
                $level,
                ['response' => 500]
            );
        }

        if (
            secure_server('REQUEST_METHOD') !== 'GET'
            || is_admin()
            || wp_doing_ajax()
            || wp_doing_cron()
            || (defined('REST_REQUEST') && REST_REQUEST)
        ) {
            return self::$cache[$cache_key] = false;
        }

        $tx_id = get_query_var(DY_CORE_CONFIRMATION_PAGE_SLUG);

        return self::$cache[$cache_key] = is_string($tx_id) && $tx_id !== '';
    }

    /**
     * Resuelve la transacción indicada en la URL y establece el post asociado
     * como contenido principal de la request.
     */
    public function regenerate_post_object(): void
    {
        if (!$this->is_confirmation_page()) {
            return;
        }

        $tx_id = (string) get_query_var(DY_CORE_CONFIRMATION_PAGE_SLUG);

        if (!wp_is_uuid($tx_id, 4)) {
            dy_errors::add(
                __('Invalid or missing transaction ID.', 'dycore')
            );

            return;
        }

        $tx = dy_tx::get_stored_tx($tx_id);

        if ($tx === null) {
            dy_errors::add(
                __('Invalid or expired transaction ID.', 'dycore')
            );

            return;
        }

        $post = get_post((int) $tx->dy_id);

        if (!$post instanceof WP_Post) {
            dy_errors::add(
                __('Invalid transaction destination.', 'dycore')
            );

            return;
        }

        self::$http_status = 200;
        self::$tx = $tx;
        self::$status = (string) $tx->status;
        $GLOBALS['post'] = $post;
    }

    /**
     * Registra el endpoint /dy-tx/{tx_id}.
     */
    public function add_rewrite_rule(): void
    {
        add_rewrite_rule(
            '^dy-tx/([^/]+)/?$',
            'index.php?dy-tx=$matches[1]',
            'top'
        );
    }

    /**
     * Registra dy-tx como query variable pública de WordPress.
     *
     * @param string[] $query_vars Query variables registradas.
     * @return string[] Query variables actualizadas.
     */
    public function register_custom_query_var(array $query_vars): array
    {
        $query_vars[] = DY_CORE_CONFIRMATION_PAGE_SLUG;

        return array_values(array_unique($query_vars));
    }

    /**
     * Sustituye el contenido del post por el estado de la transacción.
     */

    public function the_content(mixed $content): string
    {
        if (self::$tx !== null) {
            return (string) (self::$tx->confirmation['content'] ?? $content);
        }
        return is_string($content) ? $content : '';
    }


    public function wp_title(mixed $title): string
    {
        return (self::$tx !== null)
            ? (string) (self::$tx->confirmation['title'] ?? $title)
            : (is_string($title) ? $title : '');
    }

    /**
    * Sustituye el título del post dentro del loop.
    */
    public function the_title(mixed $title): string
    {
        $title = is_string($title) ? $title : '';

        return self::$tx !== null && in_the_loop()
            ? (string) (self::$tx->confirmation['title'] ?? $title)
            : $title;
    }

    /**
     * Elimina el excerpt en el endpoint de transacciones.
     */

    public function get_the_excerpt(mixed $excerpt): string
    {

        $excerpt = is_string($excerpt) ? $excerpt : '';

        return self::$tx !== null
            ? (string) (self::$tx->confirmation['excerpt'] ?? '')
            : (is_string($excerpt) ? $excerpt : '');
    }


    /**
     * Establece el código HTTP correspondiente y desactiva el cache.
     */
    public function template_redirect(): void
    {
        if (!$this->is_confirmation_page()) {
            return;
        }

        status_header(self::$http_status);
        nocache_headers();
    }

    /**
     * Evita la indexación del endpoint de transacciones.
     */
    public function meta_tags(): void
    {
        if (!$this->is_confirmation_page()) {
            return;
        }

        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }

    public function enqueue_scripts(): void
    {
        if (self::$tx === null) return;

        wp_enqueue_script('dynamicpackages-confirmation', plugin_dir_url(__FILE__) . 'js/dynamicpackages-confirmation-page.js', ['jquery'], $this->version, true);
        wp_localize_script('dynamicpackages-confirmation', 'dyPackageConfirmationArgs', [
            'textCopiedToClipBoard' => __('Copied to Clipboard!', 'dynamicpackages'),
        ]);

        wp_enqueue_script('dy_add_to_calendar', 'https://addevent.com/libs/atc/1.6.1/atc.min.js', [], null, true);
        wp_add_inline_style('minimalLayout', '.addeventatc{visibility:hidden}.addevent_container{height:42px}');
    }

    public function analytics() {

        if (self::$tx === null) return;

        $tx_id = self::$tx->tx_id;
        $events = self::$tx->confirmation['events'] ?? [];

        foreach ($events as $event) {
            $params = $event['params'];

            dy_gtag_queue_server_event(
                $event['name'], 
                $tx_id,
                $params['value'], 
                $params['currency'], 
                $params['items'] ?? []
            );
        }
    }
}