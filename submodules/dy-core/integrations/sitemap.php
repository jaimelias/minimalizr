<?php

if ( ! defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Sitemap
{
    private $query_param = null;

    /** @var string|null */
    private $changefreq_override = null;

    /** @var int */
    private $front_page_id = 0;

    /** @var int */
    private $posts_page_id = 0;

    private const QUERY_VAR = 'dy-sitemap';
    private const CHANGEFREQ_QUERY_VAR = 'changefreq';
    private const DEFAULT_POST_TYPES = 'page,post';
    private const POSTS_PER_PAGE = 200;

    private const ALLOWED_CHANGEFREQ = [
        'always',
        'hourly',
        'daily',
        'weekly',
        'monthly',
        'yearly',
        'never',
    ];

    public function __construct() {
        $this->init();
    }

    // --- inside init() ---
    public function init() {
        // keep rewrites + query var registration
        add_action('init',        [$this, 'add_rewrites']);
        add_filter('query_vars',  [$this, 'register_query_vars']);

        $query_param = secure_get(self::QUERY_VAR, null);

        if ($query_param !== null) {
            $this->query_param = $query_param;
        }

        $changefreq = secure_get(self::CHANGEFREQ_QUERY_VAR, null);

        if ($changefreq !== null) {
            $this->set_changefreq($changefreq);
        }

        // NEW: wait until parse_query to read rewrite-provided vars safely
        add_action('parse_query', [$this, 'capture_query_vars']);

        add_filter('template_include', [$this, 'run'], 100);
        add_filter('wp_headers',       [$this, 'headers'], 100);
    }

    private function set_changefreq($changefreq) {
        $this->changefreq_override = in_array(
            $changefreq,
            self::ALLOWED_CHANGEFREQ,
            true
        ) ? $changefreq : null;
    }

    public function capture_query_vars($q) {
        if (is_admin() || !$q->is_main_query()) {
            return;
        }

        $query_param = $q->get(self::QUERY_VAR);

        if (is_scalar($query_param) && !empty($query_param)) {
            $this->query_param = sanitize_text_field((string) $query_param);
        }

        $changefreq = $q->get(self::CHANGEFREQ_QUERY_VAR);

        if (is_scalar($changefreq) && !empty($changefreq)) {
            $this->set_changefreq(
                sanitize_text_field((string) $changefreq)
            );
        }
    }


    /**
     * Add rewrite rules for:
     *  /dy-sitemap/{post_type}.xml
     *  /dy-sitemap/{post_type}-{changefreq}.xml
     */
    public function add_rewrites() {
        $allowed = '(' . implode('|', self::ALLOWED_CHANGEFREQ) . ')';

        add_rewrite_rule(
            '^dy-sitemap/([^/]+)-' . $allowed . '\.xml/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]&'
                . self::CHANGEFREQ_QUERY_VAR . '=$matches[2]',
            'top'
        );

        add_rewrite_rule(
            '^dy-sitemap/([^/]+)\.xml/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /**
     * Register query vars so WP routes them through to index.php.
     */
    public function register_query_vars($vars) {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::CHANGEFREQ_QUERY_VAR;

        return $vars;
    }

    public function headers($headers) {
        if (!is_admin() && isset($this->query_param)) {
            // Keep header behavior; value kept identical to avoid changing behavior.
            $headers['Content-Type'] = 'application/xml; charset=UTF-8';
            $headers['Access-Control-Allow-Origin']  = '*';
        }
        return $headers;
    }

    public function run($template) {
        if (!isset($this->query_param)) {
            return $template;
        }

        $post_type = $this->query_param === ''
            ? self::DEFAULT_POST_TYPES
            : $this->query_param;

        $get_languages = (array) get_languages();

        $args = [
            'post_type'              => explode(',', $post_type),
            'posts_per_page'         => self::POSTS_PER_PAGE,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
            'suppress_filters'       => true,
            'post_status'            => 'publish',
        ];

        if (count($get_languages) > 0) {
            $args['lang'] = $get_languages;
        }

        $q      = new WP_Query($args);
        $output = null;

        if ($q->have_posts()) {
            $site_url        = get_site_url();
            $site_url_length = strlen($site_url);

            $this->front_page_id = (int) get_option('page_on_front');
            $this->posts_page_id = (int) get_option('page_for_posts');

            $url_format = (
                '<url>'
                . '<loc>%1$s</loc>'
                . '%2$s'
                . '<changefreq>%3$s</changefreq>'
                . '<lastmod>%4$s</lastmod>'
                . '<mobile:mobile/>'
                . '</url>'
            );

            ob_start();

            echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

            foreach ($q->posts as $post_id) {
                $url = get_permalink($post_id);

                if (substr($url, 0, $site_url_length) !== $site_url) {
                    continue;
                }

                $image_xml = '';

                if (has_post_thumbnail($post_id)) {
                    $thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');

                    if ($thumbnail_url) {
                        $image_xml = sprintf(
                            '<image:image><image:loc>%s</image:loc></image:image>',
                            esc_url(normalize_url($thumbnail_url))
                        );
                    }
                }

                $location   = esc_url(normalize_url($url));
                $changefreq = esc_html($this->changefreq_by_id($post_id));
                $lastmod    = esc_html(
                    get_the_modified_date('Y-m-d', $post_id)
                );

                echo sprintf(
                    $url_format,
                    $location,
                    $image_xml,
                    $changefreq,
                    $lastmod
                );
            }

            echo '</urlset>';

            $output = ob_get_clean();
        }

        wp_reset_query();

        exit(
            ent2ncr(
                $this->sanitize_output(
                    apply_filters('dy_sitemap', $output)
                )
            )
        );
    }

    public function sanitize_output($buffer) {
        if ($buffer === null) {
            return '';
        }

        $search  = ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s'];
        $replace = ['>', '<', '\\1'];

        return preg_replace($search, $replace, $buffer);
    }

    private function changefreq_by_id($post_id) {
        if ($this->changefreq_override !== null) {
            return $this->changefreq_override;
        }

        $alt_id = $this->polylang_alt_by_id($post_id);

        if (
            $post_id === $this->front_page_id
            || $post_id === $this->posts_page_id
            || $alt_id === $this->front_page_id
            || $alt_id === $this->posts_page_id
        ) {
            return 'daily';
        }

        return get_post_type($post_id) === 'post'
            ? 'monthly'
            : 'weekly';
    }

    private function polylang_alt_by_id($post_id) {
        if (
            function_exists('pll_get_post')
            && function_exists('pll_default_language')
        ) {
            $alt_id = pll_get_post($post_id, pll_default_language());

            return $alt_id !== false
                ? (int) $alt_id
                : false;
        }

        return false;
    }
}


?>
