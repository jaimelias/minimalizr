<?php

declare(strict_types=1);

class Dy_Confirmation_Page
{
    private string $slug = 'dy-tx';

    private static mixed $tx = null;

    /** @var array<string, bool> */
    private static array $cache = [];

    private static int $http_status = 400;

    private static string $status = 'error';

    /**
     * Registra los hooks necesarios para manejar el endpoint de transacciones.
     */
    public function __construct()
    {
        add_action('init', [$this, 'add_rewrite_rule']);

        add_filter('query_vars', [$this, 'register_custom_query_var']);


        //regenerate $post from $tx->dy_id
        add_action('wp', [$this, 'regenerate_post_object']);

        /* BELLOW THIS LINE NO CALLBACK CAN BE TRIGGERED USING "INIT" OR "ANY HOOK BEFORE INIT" */

        //converts the regenerated post into a page
        add_action('pre_get_posts', [$this, 'main_wp_query'], 100);

        //loads the page.php template
        add_filter('template_include', [$this, 'locate_template'], 100 );


        //template parts
        add_action('wp_head', [$this, 'meta_tags']);
        add_filter('the_content', [$this, 'the_content']);
        add_filter('pre_get_document_title', [$this, 'wp_title']);
        add_filter('the_title', [$this, 'the_title']);
        add_filter('get_the_excerpt', [$this, 'get_the_excerpt']);
        add_action('template_redirect', [$this, 'template_redirect']);
    }

	public function main_wp_query($query)
	{
		if(isset($query->query_vars[$this->slug]) && $query->is_main_query())
		{				
			$query->set('post_type', 'page');
			$query->set( 'posts_per_page', 1);
		}
	}

	public function locate_template($template)
	{
		if(get_query_var($this->slug))
		{
			$new_template = locate_template( [ 'page.php' ] );
			return $new_template;			
		}
		return $template;
	}

    /**
     * Determina si la request actual corresponde al endpoint /dy-tx/{tx_id}.
     */
    public function is_request_accepted(): bool
    {
        $cache_key = 'is_request_accepted';

        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
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

        $tx_id = get_query_var($this->slug);

        return self::$cache[$cache_key] = is_string($tx_id) && $tx_id !== '';
    }

    /**
     * Resuelve la transacción indicada en la URL y establece el post asociado
     * como contenido principal de la request.
     */
    public function regenerate_post_object(): void
    {
        if (!$this->is_request_accepted()) {
            return;
        }

        $tx_id = (string) get_query_var($this->slug);

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
        $query_vars[] = $this->slug;

        return array_values(array_unique($query_vars));
    }

    /**
     * Sustituye el contenido del post por el estado de la transacción.
     */
    public function the_content(mixed $content = ''): string
    {
        $content = is_string($content) ? $content : '';

        if (!$this->is_request_accepted()) {
            return $content;
        }

        return sprintf(
            '<p class="minimal_alert strong">%s</p>',
            esc_html(self::$status)
        );
    }


    /**
     * Sustituye el título del documento por el estado de la transacción.
     */
    public function wp_title(mixed $title): string
    {
        $title = is_string($title) ? $title : '';

        return $this->is_request_accepted()
            ? self::$status
            : $title;
    }

    /**
     * Sustituye el título del post dentro del loop.
     */
    public function the_title(mixed $title): string
    {
        $title = is_string($title) ? $title : '';

        return $this->is_request_accepted() && in_the_loop()
            ? self::$status
            : $title;
    }

    /**
     * Elimina el excerpt en el endpoint de transacciones.
     */
    public function get_the_excerpt(mixed $excerpt): string
    {
        $excerpt = is_string($excerpt) ? $excerpt : '';

        return $this->is_request_accepted()
            ? ''
            : $excerpt;
    }

    /**
     * Establece el código HTTP correspondiente y desactiva el cache.
     */
    public function template_redirect(): void
    {
        if (!$this->is_request_accepted()) {
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
        if (!$this->is_request_accepted()) {
            return;
        }

        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}