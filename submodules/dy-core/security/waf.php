<?php 

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dy_WAF {
    protected $max_array_depth = 6;

    public function __construct() {
        // Earliest hook after WordPress normalizes request slashes and creates $wp.
        add_action('setup_theme', [$this, 'validate_params'], PHP_INT_MIN);
    }

    private function reject_param($message) {
        error_log("[WAF - Bad Request] = $message");
        wp_die(esc_html($message), 'WAF - Bad Request', ['response' => 400]);
        exit;
    }

    private function format_param_name($name) {
        $name = preg_replace('/[^\x20-\x7E]/', '?', (string) $name);
        $name = is_string($name) ? $name : '';

        return $name !== '' ? substr($name, 0, 191) : '(empty)';
    }

    private function validate_non_scalar_param($param_key, $path, $value, $depth = 1) {
        if (!is_array($value)) {
            $message = sprintf(
                '%s param %s type %s is not allowed',
                $param_key,
                $path,
                gettype($value)
            );
            $this->reject_param($message);
        }

        $max_depth = max(1, (int) $this->max_array_depth);

        if ($depth > $max_depth) {
            $message = sprintf(
                '%s param %s array depth > %d',
                $param_key,
                $path,
                $max_depth
            );
            $this->reject_param($message);
        }

        foreach ($value as $key => $item) {
            if (is_scalar($item)) {
                continue;
            }

            $child_path = sprintf(
                '%s[%s]',
                $path,
                $this->format_param_name($key)
            );

            $this->validate_non_scalar_param(
                $param_key,
                $child_path,
                $item,
                $depth + 1
            );
        }
    }

    public function validate_params() {

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

        $sanitize_allowed_value = function($param_key, $key, $value, $spec) {
            $limit = isset($spec['max_length']) ? (int) $spec['max_length'] : 300;

            $sanitizer = isset($spec['sanitizer']) && is_string($spec['sanitizer'])
                ? $spec['sanitizer']
                : 'sanitize_text_field';

            if (!function_exists($sanitizer)) {
                $sanitizer = 'sanitize_text_field';
            }

            if (!is_scalar($value)) {
                // Every non-scalar value was already checked by the recursive guard.
                return $value;
            }

            $key_name = $this->format_param_name($key);

            $sanitize_scalar = function($item) use ($param_key, $key_name, $limit, $sanitizer) {
                $raw = (string) $item;

                if (
                    $key_name === 'page' &&
                    $param_key === 'get' &&
                    $raw !== '' &&
                    !ctype_digit($raw) &&
                    is_admin() &&
                    basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin.php'
                ) {
                    $limit = 191;
                }

                $len = function_exists('mb_strlen')
                    ? mb_strlen($raw, 'UTF-8')
                    : strlen($raw);

                if ($len === false) {
                    $len = strlen($raw);
                }

                if ($len > $limit) {
                    $message = "{$param_key} param {$key_name} length > {$limit}";
                    $this->reject_param($message);
                }

                return (string) call_user_func($sanitizer, $raw);
            };

            return $sanitize_scalar($value);
        };

        $default_params = [
            'post'   => (array) $this->default_post_params([]),
            'get'    => (array) $this->default_get_params([]),
            'cookie' => (array) $this->default_cookie_params([]),
        ];

        // Unslash once at the source
        $sg_post   = wp_unslash($_POST);
        $sg_get    = wp_unslash($_GET);
        $sg_cookie = wp_unslash($_COOKIE);

        $superglobals = [
            'post'   => $sg_post,
            'get'    => $sg_get,
            'cookie' => $sg_cookie,
        ];

        $validated_sources = [
            'post'   => [],
            'get'    => [],
            'cookie' => [],
        ];

        foreach ($default_params as $param_key => $allowed_keys) {

            $allowed = $normalize_allowed($allowed_keys);
            $exact   = $allowed['EXACT'];
            $prefix  = $allowed['PREFIX'];

            // Match the most-specific prefix first (e.g. comment_author_email_ before comment_author_).
            usort($prefix, static function($a, $b) {
                return strlen($b[0]) <=> strlen($a[0]);
            });

            foreach ((array) $superglobals[$param_key] as $key => $value) {
                if (!is_scalar($value)) {
                    $this->validate_non_scalar_param(
                        $param_key,
                        $this->format_param_name($key),
                        $value
                    );
                }

                // Find spec: exact first, then prefix
                $spec = $exact[$key] ?? null;
                if ($spec === null && !empty($prefix)) {
                    foreach ($prefix as [$pfx, $pfxSpec]) {
                        if ($starts_with($key, $pfx)) { $spec = $pfxSpec; break; }
                    }
                }
                if ($spec === null) { continue; }

                $clean = $sanitize_allowed_value($param_key, $key, $value, $spec);
                if ($clean === null) { continue; }

                $validated_sources[$param_key][$key] = true;
            }
        }

        // Cookie-free request view; POST wins over GET.
        // Known POST/GET params are already validated above.
        $request_view = $sg_post + $sg_get;

        $request_allowed = $normalize_allowed((array) $this->default_request_params([]));
        $request_exact   = $request_allowed['EXACT'];
        $request_prefix  = $request_allowed['PREFIX'];

        usort($request_prefix, static function($a, $b) {
            return strlen($b[0]) <=> strlen($a[0]);
        });

        foreach ((array) $request_view as $key => $value) {

            // `$request_view` uses POST + GET, so POST is the winning source.
            $request_source = array_key_exists($key, $sg_post) ? 'post' : 'get';

            if (isset($validated_sources[$request_source][$key])) {
                continue;
            }


            $spec = $request_exact[$key] ?? null;

            
            if ($spec === null && !empty($request_prefix)) {
                foreach ($request_prefix as [$pfx, $pfxSpec]) {
                    if ($starts_with($key, $pfx)) { $spec = $pfxSpec; break; }
                }
            }
            if ($spec === null) { continue; }

            $clean = $sanitize_allowed_value('request', $key, $value, $spec);
            if ($clean === null) { continue; }

        }

    }


    public function default_get_params($arr = []) {
        // 1) Pull known public query vars (include 3rd-party via filter)
        global $wp;
        $public_vars = [];
        if (isset($wp) && is_array($wp->public_query_vars)) {
            $public_vars = $wp->public_query_vars;
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

            'has_published_posts' => [
                'max_length' => 20,
                    'max_items'  => 100,
                    'sanitizer'  => 'sanitize_key',
            ]
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
            'cf_clearance' => ['max_length' => 768,   'sanitizer' => 'sanitize_text_field'],

            'PHPSESSID' => ['max_length' => 128,  'sanitizer' => 'sanitize_text_field'],
        ], $arr);

    }

    public function default_request_params($arr = []) {
        // Request is deliberately POST + GET only.
        $get = (array) $this->default_get_params([]);
        $post = (array) $this->default_post_params([]);
        $cookie = (array) $this->default_cookie_params([]);

        // Merge while preserving more-specific definitions (POST > GET > COOKIE)
        // array_replace keeps rightmost duplicates; adjust order to taste.
        $merged = array_replace($cookie, $get, $post);

        // Add extra public tokens/selectors common in AJAX/forms
        $extras = [
            '_ajax_nonce' => ['max_length' => 12,    'sanitizer' => 'sanitize_text_field'],
            'cf-turnstile-response' => ['max_length' => 2048,  'sanitizer' => 'sanitize_text_field'],
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
