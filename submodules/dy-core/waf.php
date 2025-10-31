<?php 


#[AllowDynamicProperties]
class Dy_WAF {
    function __construct() {
        $this->valid_arrays_or_objects = ['has_published_posts'];
        add_filter('dy_default_get_params', [$this, 'default_get_params']); //new params will be send from different wp plugins
        add_filter('dy_default_post_params', [$this, 'default_post_params']);
        add_filter('dy_default_request_params', [$this, 'default_request_params']);
        add_filter('dy_default_cookie_params', [$this, 'default_cookie_params']);
        add_action( 'init', [$this, 'validate_params']);
    }

    public function validate_params() {

        if(is_admin()) return;

        // Helper: safe "starts with" for PHP 7+
        $starts_with = static function($haystack, $prefix) {
            return $prefix !== '' && strncmp($haystack, $prefix, strlen($prefix)) === 0;
        };

        // Helper: normalize allowlist (supports flat arrays for BC and structured maps)
        // Returns ['EXACT' => [key => spec...], 'PREFIX' => [[prefix, spec]...]]
        $normalize_allowed = static function(array $allowed) {
            $exact = [];
            $prefix = [];

            $is_flat = true;
            foreach ($allowed as $k => $v) {
                if (!is_int($k)) { $is_flat = false; break; }
            }

            if ($is_flat) {
                foreach ($allowed as $name) {
                    $exact[$name] = ['max_length' => 300, 'sanitizer' => 'sanitize_text_field'];
                }
                return ['EXACT' => $exact, 'PREFIX' => $prefix];
            }

            // Structured map (but may contain int=>string items from mixed merges)
            foreach ($allowed as $name => $spec) {
                if (is_int($name) && is_string($spec)) { // << handle mixed case
                    $name = $spec;
                    $spec = [];
                }
                $spec = is_array($spec) ? $spec : [];
                $spec += ['max_length' => 300, 'sanitizer' => 'sanitize_text_field'];

                if (!empty($spec['prefix'])) {
                    $prefix[] = [$name, $spec];
                } else {
                    $exact[$name] = $spec;
                }
            }
            return ['EXACT' => $exact, 'PREFIX' => $prefix];
        };


        // Build containers
        $dy_params = (object) [
            'post'    => [],
            'get'     => [],
            'request' => [],
            'cookie'  => [],
        ];

        $default_params = [
            'post'    => (array) apply_filters('dy_default_post_params', []),
            'get'     => (array) apply_filters('dy_default_get_params', []),
            'request' => (array) apply_filters('dy_default_request_params', []),
            'cookie'  => (array) apply_filters('dy_default_cookie_params', []),
        ];

        // Unslash once at the source
        $sg_post   = wp_unslash($_POST);
        $sg_get    = wp_unslash($_GET);
        $sg_cookie = wp_unslash($_COOKIE);

        // Cookie-free request view; POST wins over GET
        $request_view = $sg_post + $sg_get;

        $superglobals = [
            'post'    => $sg_post,
            'get'     => $sg_get,
            'request' => $request_view,
            'cookie'  => $sg_cookie,
        ];

        foreach ($default_params as $param_key => $allowed_keys) {

            $allowed = $normalize_allowed($allowed_keys);
            $exact   = $allowed['EXACT'];
            $prefix  = $allowed['PREFIX'];

            foreach ((array) $superglobals[$param_key] as $key => $value) {
                // Skip only true empties; keep "0"
                if ($value === '' || $value === null) {
                    continue;
                }

                // Reject non-scalars (arrays/objects/resources)
                if (!is_scalar($value) && !in_array($key, $this->valid_arrays_or_objects)) {
                    $message = "Invalid {$param_key} param is array or object: {$key}";
                    //cloudflare_ban_ip_address($message);
                    //wp_die($message);
                    continue;
                }

                // Find spec: exact first, then prefix
                $spec = $exact[$key] ?? null;
                if ($spec === null && !empty($prefix)) {
                    foreach ($prefix as [$pfx, $pfxSpec]) {
                        if ($starts_with($key, $pfx)) { $spec = $pfxSpec; break; }
                    }
                }
                if ($spec === null) { continue; }

                // Sanitize first, then length-check
                $sanitizer = (isset($spec['sanitizer']) && is_string($spec['sanitizer'])) ? $spec['sanitizer'] : 'sanitize_text_field';
                if (!function_exists($sanitizer)) { $sanitizer = 'sanitize_text_field'; }

                $clean = call_user_func($sanitizer, (string) $value);

                $limit = isset($spec['max_length']) ? (int) $spec['max_length'] : 300;
                $len   = function_exists('mb_strlen') ? mb_strlen($clean, 'UTF-8') : strlen($clean);
                if ($len > $limit) {
                    $message = "Invalid {$param_key} param length: {$key} ({$len} greater than {$limit})";
                    cloudflare_ban_ip_address($message);
                    wp_die($message);
                }

                $dy_params->{$param_key}[$key] = (string) $clean;
            }
        }

        $GLOBALS['dy_params'] = $dy_params;
    }


    public function default_get_params($arr = []) {
        // 1) Pull known public query vars (include 3rd-party via filter)
        global $wp;
        $public_vars = [];
        if (isset($wp) && is_array($wp->public_query_vars)) {
            // Apply the same filter core uses so plugins that add query vars are included
            $public_vars = (array) apply_filters('query_vars', $wp->public_query_vars);
        }

        // 2) Include taxonomy query vars (public taxonomies only)
        $tax_objects = get_taxonomies(['public' => true], 'objects');
        foreach ($tax_objects as $tax) {
            if (!empty($tax->query_var)) {
                $public_vars[] = $tax->query_var;
            }
        }

        // Deduplicate while preserving order
        $public_vars = array_values(array_unique($public_vars));

        // 3) Start with a sane default spec for every public var
        $defaults = [];
        foreach ($public_vars as $var) {
            // default sanitizer is conservative text
            $defaults[$var] = [
                'max_length' => 300,
                'sanitizer'  => 'sanitize_text_field',
            ];
        }

        // 4) Targeted, stricter overrides for well-known vars
        $overrides = [
            // Search
            's'             => ['max_length' => 128,  'sanitizer' => 'sanitize_text_field'],

            // Pagination
            'paged'         => ['max_length' => 6,    'sanitizer' => 'sanitize_text_field'],
            'page'          => ['max_length' => 6,    'sanitizer' => 'sanitize_text_field'],
            'cpage'         => ['max_length' => 6,    'sanitizer' => 'sanitize_text_field'],

            // Dates / time
            'year'          => ['max_length' => 4,    'sanitizer' => 'sanitize_text_field'],
            'monthnum'      => ['max_length' => 2,    'sanitizer' => 'sanitize_text_field'],
            'day'           => ['max_length' => 2,    'sanitizer' => 'sanitize_text_field'],
            'm'             => ['max_length' => 6,    'sanitizer' => 'sanitize_text_field'], // yyyymm

            // Posts & Pages
            'p'             => ['max_length' => 11,   'sanitizer' => 'sanitize_text_field'],
            'name'          => ['max_length' => 200,  'sanitizer' => 'sanitize_text_field'],
            'pagename'      => ['max_length' => 200,  'sanitizer' => 'sanitize_text_field'],
            'page_id'       => ['max_length' => 11,   'sanitizer' => 'sanitize_text_field'],
            'attachment_id' => ['max_length' => 11,   'sanitizer' => 'sanitize_text_field'],
            'attachment'    => ['max_length' => 200,  'sanitizer' => 'sanitize_text_field'],

            // Taxonomies
            'cat'           => ['max_length' => 11,   'sanitizer' => 'sanitize_text_field'],
            'category_name' => ['max_length' => 200,  'sanitizer' => 'sanitize_text_field'],
            'tag'           => ['max_length' => 200,  'sanitizer' => 'sanitize_text_field'],
            'tag_id'        => ['max_length' => 11,   'sanitizer' => 'sanitize_text_field'],
            'taxonomy'      => ['max_length' => 64,   'sanitizer' => 'sanitize_text_field'],
            'term'          => ['max_length' => 200,  'sanitizer' => 'sanitize_text_field'],

            // Authors
            'author'        => ['max_length' => 11,   'sanitizer' => 'sanitize_text_field'],
            'author_name'   => ['max_length' => 60,   'sanitizer' => 'sanitize_text_field'],

            // Ordering
            'orderby'       => ['max_length' => 50,   'sanitizer' => 'sanitize_text_field'],
            'order'         => ['max_length' => 4,    'sanitizer' => 'sanitize_text_field'], // asc|desc

            // Feeds & formats
            'feed'          => ['max_length' => 16,   'sanitizer' => 'sanitize_text_field'],
            'withcomments'  => ['max_length' => 8,    'sanitizer' => 'sanitize_text_field'],

            // Misc
            'error'         => ['max_length' => 32,   'sanitizer' => 'sanitize_text_field'],
            // If you expose URLs via query vars (rare), you can force URL sanitizer:
            // 'redirect_to' => ['max_length' => 2048, 'sanitizer' => 'esc_url_raw'],
        ];

        // Apply overrides for any var we know about
        foreach ($overrides as $k => $spec) {
            if (isset($defaults[$k])) {
                $defaults[$k] = array_replace($defaults[$k], $spec);
            } else {
                // If the var isn't in public_vars but you still want to allow it:
                $defaults[$k] = $spec;
            }
        }

        // 5) Let caller-provided $arr win (your established pattern)
        return array_replace($defaults, $arr);
    }


    public function default_post_params($arr) {
        return array_replace([
            // Login (wp-login.php)
            'log' => ['max_length' => 60,    'sanitizer' => 'sanitize_text_field'],
            'pwd' => ['max_length' => 128,   'sanitizer' => 'sanitize_text_field'],
            'rememberme' => ['max_length' => 16,    'sanitizer' => 'sanitize_text_field'], // "1"|"forever"
            'redirect_to' => ['max_length' => 2048,  'sanitizer' => 'esc_url_raw'],
            'testcookie' => ['max_length' => 32,    'sanitizer' => 'sanitize_text_field'],

            // Registration (if enabled publicly)
            'user_login' => ['max_length' => 60,    'sanitizer' => 'sanitize_text_field'],
            'user_email' => ['max_length' => 254,   'sanitizer' => 'sanitize_email'],
            'user_pass' => ['max_length' => 128,   'sanitizer' => 'sanitize_text_field'],
            'user_pass2' => ['max_length' => 128,   'sanitizer' => 'sanitize_text_field'],

            // Password Reset / Lost Password
            // (user_login appears again in core flows)
            'pass1' => ['max_length' => 128,   'sanitizer' => 'sanitize_text_field'],
            'pass2' => ['max_length' => 128,   'sanitizer' => 'sanitize_text_field'],
            'rp_key' => ['max_length' => 64,    'sanitizer' => 'sanitize_text_field'],

            // Commenting (wp-comments-post.php)
            'comment' => ['max_length' => 10000, 'sanitizer' => 'sanitize_textarea_field'],
            'author' => ['max_length' => 60,    'sanitizer' => 'sanitize_text_field'],
            'email' => ['max_length' => 254,   'sanitizer' => 'sanitize_email'],
            'url' => ['max_length' => 2048,  'sanitizer' => 'esc_url_raw'],
            'comment_post_ID' => ['max_length' => 11,    'sanitizer' => 'sanitize_text_field'],
            'comment_parent' => ['max_length' => 11,    'sanitizer' => 'sanitize_text_field'],

            // Security tokens (appear in most forms)
            '_wpnonce' => ['max_length' => 12,    'sanitizer' => 'sanitize_text_field'],
            '_wp_http_referer'=> ['max_length' => 2048,  'sanitizer' => 'esc_url_raw'],

            // General form action
            'action' => ['max_length' => 64,    'sanitizer' => 'sanitize_text_field'],
            'submit' => ['max_length' => 32,    'sanitizer' => 'sanitize_text_field'],
        ], $arr);
    }

    public function default_cookie_params($arr) {
        return array_replace([
            // Core WP cookies (front-end)
            'wordpress_test_cookie' => ['max_length' => 64,    'sanitizer' => 'sanitize_text_field'],

            // Commenter convenience cookies (public commenting)
            'comment_author' => ['max_length' => 60,    'sanitizer' => 'sanitize_text_field'],
            'comment_author_email' => ['max_length' => 254,   'sanitizer' => 'sanitize_email'],
            'comment_author_url' => ['max_length' => 2048,  'sanitizer' => 'esc_url_raw'],

            // Hashed variants (prefix matches)
            'comment_author_' => ['max_length' => 60,    'sanitizer' => 'sanitize_text_field', 'prefix' => true],
            'comment_author_email_' => ['max_length' => 254,   'sanitizer' => 'sanitize_email',       'prefix' => true],
            'comment_author_url_' => ['max_length' => 2048,  'sanitizer' => 'esc_url_raw',          'prefix' => true],

            // User preferences (prefix; user id appended)
            'wp-settings-' => ['max_length' => 128,    'sanitizer' => 'sanitize_text_field', 'prefix' => true],
            'wp-settings-time-' => ['max_length' => 128,    'sanitizer' => 'sanitize_text_field', 'prefix' => true],

            // Logged-in / auth (prefix; hash appended)
            'wordpress_logged_in_' => ['max_length' => 255,   'sanitizer' => 'sanitize_text_field', 'prefix' => true],
            'wordpress_sec_' => ['max_length' => 255,   'sanitizer' => 'sanitize_text_field', 'prefix' => true],

            // WooCommerce (public storefronts)
            'woocommerce_cart_hash' => ['max_length' => 64,    'sanitizer' => 'sanitize_text_field'],
            'woocommerce_items_in_cart' => ['max_length' => 6,     'sanitizer' => 'sanitize_text_field'],
            'wp_woocommerce_session' => ['max_length' => 128,   'sanitizer' => 'sanitize_text_field', 'prefix' => true], // wp_woocommerce_session_{HASH}

            // CF / infra (often present)
            'cf_chl_' => ['max_length' => 512,   'sanitizer' => 'sanitize_text_field', 'prefix' => true],
            'cf_clearance' => ['max_length' => 512,   'sanitizer' => 'sanitize_text_field'],

            'PHPSESSID' => ['max_length' => 128,  'sanitizer' => 'sanitize_text_field'],
        ], $arr);

    }

    public function default_request_params($arr = []) {
        // Start with structured GET/POST/COOKIE maps
        $get = (array) apply_filters('dy_default_get_params', []);
        $post = (array) apply_filters('dy_default_post_params', []);
        $cookie = (array) apply_filters('dy_default_cookie_params', []);

        // Merge while preserving more-specific definitions (POST > GET > COOKIE)
        // array_replace keeps rightmost duplicates; adjust order to taste.
        $merged = array_replace($cookie, $get, $post);

        // Add extra public tokens/selectors common in AJAX/forms
        $extras = [
            '_ajax_nonce' => ['max_length' => 12,    'sanitizer' => 'sanitize_text_field'],
            'g-recaptcha-response' => ['max_length' => 2048,  'sanitizer' => 'sanitize_text_field'],
            'action' => ['max_length' => 64,    'sanitizer' => 'sanitize_text_field'],
            'submit' => ['max_length' => 32,    'sanitizer' => 'sanitize_text_field'],
        ];

        // Ensure canonical presence of certain keys used across flows
        // (these may already exist from GET/POST; extras will not override existing)
        foreach ($extras as $k => $v) {
            if (!isset($merged[$k])) {
                $merged[$k] = $v;
            }
        }

        return array_replace($merged, $arr);
    }

}


?>