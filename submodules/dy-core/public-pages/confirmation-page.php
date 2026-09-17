<?php

declare(strict_types=1);

if (!defined('WPINC')) {
    exit;
}

class Dy_Confirmation_Page
{
    private const REWRITE_VERSION = '2';

    private string $slug = 'dy-tx';

    private string $version;

    private ?array $tx = null;

    private ?array $confirmation_result = null;

    private int $http_status = 400;

    public function __construct(int|string $version = '')
    {
        $this->version = (string) $version;

        add_action('init', [$this, 'add_rewrite_rule']);
        add_action('init', [$this, 'add_localized_rewrite_rules'], 20);
        add_action('wp_loaded', [$this, 'refresh_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_custom_query_var']);


        
        //converts the regenerated post into a page
        add_action('pre_get_posts', [$this, 'main_wp_query'], 100);
        add_filter('posts_pre_query', [$this, 'transaction_post'], 10, 2);
        add_action('wp', [$this, 'set_post_on_checkout_page']);
        add_action('wp', [$this, 'prepare_confirmation'], 20);

        add_filter('template_include', [$this, 'locate_template'], 100);
        add_filter('redirect_canonical', [$this, 'redirect_canonical']);
        add_filter('the_content', [$this, 'the_content'], 101);
        add_filter('pre_get_document_title', [$this, 'wp_title'], 101);
        add_filter('the_title', [$this, 'the_title'], 101);
        add_filter('get_the_excerpt', [$this, 'get_the_excerpt'], 101, 2);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'meta_tags']);
        add_action('template_redirect', [$this, 'template_redirect']);
    }

    public static function is_confirmation(): bool
    {
        if (
            secure_server('REQUEST_METHOD') !== 'GET'
            || is_admin()
            || wp_doing_ajax()
            || wp_doing_cron()
            || (defined('REST_REQUEST') && REST_REQUEST)
        ) {
            return false;
        }

        $tx_id = get_query_var('dy-tx');

        return is_string($tx_id) && $tx_id !== '';
    }

	public function main_wp_query($query)
	{
		if(isset($query->query_vars[$this->slug]) && $query->is_main_query())
		{				
			$query->set('post_type', 'page');
			$query->set( 'posts_per_page', 1);
		}
	}

    /** Backward-compatible instance predicate. */
    public function is_request_accepted(): bool
    {
        return self::is_confirmation();
    }

    public function add_rewrite_rule(): void
    {
        add_rewrite_rule(
            '^dy-tx/([^/]+)/?$',
            'index.php?dy-tx=$matches[1]',
            'top'
        );
    }

    public function add_localized_rewrite_rules(): void
    {
        if (!function_exists('pll_languages_list')) {
            return;
        }

        $languages = array_map(
            static fn(string $language): string => preg_quote($language, '#'),
            get_languages()
        );

        if ($languages === []) {
            return;
        }

        add_rewrite_rule(
            '^(' . implode('|', $languages) . ')/dy-tx/([^/]+)/?$',
            'index.php?lang=$matches[1]&dy-tx=$matches[2]',
            'top'
        );
    }

    public function refresh_rewrite_rules(): void
    {
        if (dy_get_option('dy_checkout_rewrite_version') === self::REWRITE_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(
            'dy_checkout_rewrite_version',
            self::REWRITE_VERSION,
            false
        );
    }

    /**
     * @param string[] $query_vars Registered public query variables.
     * @return string[]
     */
    public function register_custom_query_var(array $query_vars): array
    {
        $query_vars[] = $this->slug;

        return array_values(array_unique($query_vars));
    }


    /**
     * Supply the transaction destination to the main loop before WordPress
     * attempts to query a synthetic page from the endpoint query variable.
     *
     * @param WP_Post[]|null $posts Short-circuited posts, when already set.
     * @return WP_Post[]|null
     */
    public function transaction_post(?array $posts, WP_Query $query): ?array
    {
        if (!$query->is_main_query() || !self::is_confirmation()) {
            return $posts;
        }

        $post = $this->resolve_transaction_post();

        if (!$post instanceof WP_Post) {
            // Keep a loop available so dy_errors can render an invalid or
            // expired transaction through the normal page template.
            $post = new WP_Post((object) [
                'ID' => 0,
                'post_author' => 0,
                'post_content' => '',
                'post_excerpt' => '',
                'post_name' => '',
                'post_parent' => 0,
                'post_status' => 'publish',
                'post_title' => '',
                'post_type' => 'page',
            ]);
        }

        $query->found_posts = 1;
        $query->max_num_pages = 1;
        $query->queried_object = $post;
        $query->queried_object_id = $post->ID;

        return [$post];
    }

    public function set_post_on_checkout_page(): void
    {
        if (!self::is_confirmation()) {
            return;
        }

        $post = $this->resolve_transaction_post();

        if (!$post instanceof WP_Post) {
            $this->add_transaction_error();
            return;
        }

        $this->http_status = 200;
        $GLOBALS['post'] = $post;
    }

    public function prepare_confirmation(): void
    {
        if (!self::is_confirmation() || $this->tx === null) {
            return;
        }

        $events = dy_tx::events($this->tx);
        $cookie = 'dy_tx_seen_' . $this->tx['tx_id'];

        if (cookie_has($cookie) || !is_array($events) || $events === []) {
            return;
        }

        foreach ($events as $event) {
            if (!is_array($event) || !is_array($event['params'] ?? null)) {
                continue;
            }

            $params = $event['params'];

            dy_gtag_queue_server_event(
                (string) ($event['name'] ?? ''),
                (string) $this->tx['tx_id'],
                (float) ($params['value'] ?? 0),
                (string) ($params['currency'] ?? ''),
                is_array($params['items'] ?? null) ? $params['items'] : []
            );
        }

        setcookie(
            $cookie,
            '1',
            [
                'expires' => time() + DAY_IN_SECONDS,
                'path' => (string) (wp_parse_url(home_lang(), PHP_URL_PATH) ?: '/'),
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public function locate_template(string $template): string
    {
        if (!self::is_confirmation()) {
            return $template;
        }

        return locate_template(['page.php']) ?: $template;
    }

    public function redirect_canonical(string|false $url): string|false
    {
        return self::is_confirmation() ? false : $url;
    }

    public function the_content(mixed $content = ''): string
    {
        $content = is_string($content) ? $content : '';

        if (!self::is_confirmation() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $confirmation = $this->confirmation();

        if (array_key_exists('content', $confirmation)) {
            return (string) $confirmation['content'];
        }

        return sprintf(
            '<p class="minimal_alert strong">%s</p>',
            esc_html((string) ($this->tx['status'] ?? 'error'))
        );
    }

    public function wp_title(mixed $title): string
    {
        $title = is_string($title) ? $title : '';

        if (!self::is_confirmation()) {
            return $title;
        }

        $confirmation = $this->confirmation();

        return array_key_exists('title', $confirmation)
            ? (string) $confirmation['title']
            : (string) ($this->tx['status'] ?? $title);
    }

    public function the_title(mixed $title): string
    {
        $title = is_string($title) ? $title : '';

        return self::is_confirmation() && in_the_loop() && is_main_query()
            ? $this->wp_title($title)
            : $title;
    }

    public function get_the_excerpt(mixed $excerpt, mixed $post = null): string
    {
        $excerpt = is_string($excerpt) ? $excerpt : '';

        if (!self::is_confirmation() || !$post instanceof WP_Post || $this->tx === null) {
            return $excerpt;
        }

		$transaction_post_id = (int) dy_tx::service_value('dy_id', 0, $this->tx);
		if ((int) $post->ID !== $transaction_post_id) {
			return $excerpt;
		}

        return (string) ($this->confirmation()['excerpt'] ?? '');
    }

    public function enqueue_scripts(): void
    {
        if (!self::is_confirmation()) {
            return;
        }

        $core_url = plugin_dir_url(dirname(__DIR__) . '/loader.php');

        wp_enqueue_script(
            'dy-core-confirmation',
            $core_url . 'js/dy-core-confirmation-page.js',
            ['jquery'],
            $this->version !== '' ? $this->version : null,
            true
        );
        wp_localize_script(
            'dy-core-confirmation',
            'dyConfirmationArgs',
            [
                'textCopiedToClipboard' => __('Copied to Clipboard!', 'dycore'),
            ]
        );

        if (str_contains((string) ($this->confirmation()['content'] ?? ''), 'addeventatc')) {
            wp_enqueue_script(
                'dy_add_to_calendar',
                'https://addevent.com/libs/atc/1.6.1/atc.min.js',
                [],
                null,
                true
            );
            wp_add_inline_style(
                'minimalLayout',
                '.addeventatc{visibility:hidden}.addevent_container{height:42px}'
            );
        }
    }

    public function template_redirect(): void
    {
        if (!self::is_confirmation()) {
            return;
        }

        status_header($this->http_status);
		header('Referrer-Policy: no-referrer');
        nocache_headers();
    }

    public function meta_tags(): void
    {
        if (self::is_confirmation()) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
			echo '<meta name="referrer" content="no-referrer">' . "\n";
        }
    }

    private function resolve_transaction_post(): ?WP_Post
    {
        if ($this->tx !== null) {
            dy_tx::set_current_transaction($this->tx);
            $post = Dy_Checkout::source(Dy_Checkout::source_id($this->tx))?->confirmation_post($this->tx);

            return $post instanceof WP_Post ? $post : null;
        }

        dy_tx::set_current_transaction(null);

        $tx_id = (string) get_query_var($this->slug);

        if (!wp_is_uuid($tx_id, 4)) {
            return null;
        }

        $tx = dy_tx::get_stored_tx($tx_id);

        if ($tx === null || !dy_tx::validate($tx_id, $tx)) {
            return null;
        }

		if (!in_array((string) ($tx['status'] ?? ''), ['success', 'declined', 'error'], true)) {
			return null;
		}

        $source = Dy_Checkout::source(Dy_Checkout::source_id($tx));
        $post = $source?->confirmation_post($tx);
        if (!$post instanceof WP_Post) return null;

        dy_tx::set_current_transaction($tx);

        $is_valid_destination = (bool) apply_filters(
            'dy_confirmation_destination_is_valid',
            true,
            $post,
            $tx
        );

        if (!$is_valid_destination) {
            dy_tx::set_current_transaction(null);
            return null;
        }

        $this->tx = $tx;

        return $post;
    }

    private function add_transaction_error(): void
    {
        if (!wp_is_uuid((string) get_query_var($this->slug), 4)) {
            dy_errors::add(__('Invalid or missing transaction ID.', 'dycore'));
            return;
        }

        dy_errors::add(__('Invalid or expired transaction ID.', 'dycore'));
    }

    /** @return array<string, mixed> */
    private function confirmation(): array
    {
        if ($this->tx === null) {
            return [];
        }

        if ($this->confirmation_result !== null) {
            return $this->confirmation_result;
        }

        $confirmation = [];
        $filtered = apply_filters('dy_confirmation_result', $confirmation, $this->tx);

        if (is_array($filtered)) {
            $confirmation = $filtered;
        }

        $request_type = sanitize_key(
            (string) dy_tx::service_value('dy_request', '', $this->tx)
        );

        if ($request_type !== '') {
            $filtered = apply_filters(
                'dy_confirmation_result_' . $request_type,
                $confirmation,
                $this->tx
            );

            if (is_array($filtered)) {
                $confirmation = $filtered;
            }
        }

        return $this->confirmation_result = $confirmation;
    }
}
