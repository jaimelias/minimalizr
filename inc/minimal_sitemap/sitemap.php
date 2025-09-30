<?php
if (!class_exists('minimal_sitemap')) {
	#[AllowDynamicProperties]
	class minimal_sitemap
	{
		/** @var string|null */
		private $query_param = null;

		/** @var array|false Cached Polylang language slugs or false */
		private $pll_langs = false;

		public function __construct() {
			$this->init();
		}

		public function init() {
			/**
			 * 1) Pretty URL support (rewrite + query vars)
			 */
			add_action('init',        [$this, 'add_rewrites']);
			add_filter('query_vars',  [$this, 'register_query_vars']);

			/**
			 * 2) Preserve existing GET-based behavior
			 *    but also read from query vars populated by the rewrites.
			 */
			$qp = isset($_GET['minimal-sitemap']) ? sanitize_text_field($_GET['minimal-sitemap']) : get_query_var('minimal-sitemap', null);
			if (!empty($qp)) {
				$this->query_param = sanitize_text_field($qp);
			}

			// If rewrite provided changefreq, mirror it into $_GET so downstream logic remains identical.
			$cf = isset($_GET['changefreq']) ? sanitize_text_field($_GET['changefreq']) : get_query_var('changefreq', null);
			if (!empty($cf)) {
				$_GET['changefreq'] = sanitize_text_field($cf);
			}

			add_filter('template_include', [$this, 'run'], 100);
			add_filter('wp_headers',       [$this, 'headers'], 100);

			// (Optional) keep rewrites fresh if code changes without activation.
			// remove the next line in production if you prefer manual flushing.
			// flush_rewrite_rules(false);
		}

		/**
		 * Add rewrite rules for:
		 *  /minimal-sitemap/{post_type}.xml
		 *  /minimal-sitemap/{post_type}-{changefreq}.xml
		 */
		public function add_rewrites() {
			$allowed = '(always|hourly|daily|weekly|monthly|yearly|never)';
			// With changefreq suffix
			add_rewrite_rule(
				'^minimal-sitemap/([^/]+)-' . $allowed . '\.xml/?$',
				'index.php?minimal-sitemap=$matches[1]&changefreq=$matches[2]',
				'top'
			);
			// Without changefreq suffix
			add_rewrite_rule(
				'^minimal-sitemap/([^/]+)\.xml/?$',
				'index.php?minimal-sitemap=$matches[1]',
				'top'
			);
		}

		/**
		 * Register query vars so WP routes them through to index.php.
		 */
		public function register_query_vars($vars) {
			$vars[] = 'minimal-sitemap';
			$vars[] = 'changefreq';
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

			$post_type = $this->query_param;
			if ($post_type === '') {
				$post_type = 'page,post';
			}

			// Cache Polylang languages once per request.
			$this->pll_langs = $this->polylang();

			$args = [
				'post_type'        => explode(',', $post_type),
				'posts_per_page'   => 200,
				'no_found_rows'    => true,     // performance
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'           => 'ids',    // fetch only IDs; we’ll use ID-based getters
				'suppress_filters' => true,     // WPML all languages
				'post_status'      => 'publish' // explicit; matches public output in most cases
			];

			// Polylang all languages, if available (kept behavior: pass array of slugs)
			if ($this->pll_langs) {
				$args['lang'] = $this->pll_langs;
			}

			$q = new WP_Query($args);

			$output = null;
			if ($q->have_posts()) {
				$site_url = get_site_url();

				ob_start();
				echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

				foreach ($q->posts as $post_id) {
					$url = get_permalink($post_id);

					// Keep original site URL prefix check logic
					if (substr($url, 0, strlen($site_url)) === $site_url) {
						echo '<url>';
						echo '<loc>' . esc_url(normalize_url($url)) . '</loc>';

						if (has_post_thumbnail($post_id)) {
							$thumb = get_the_post_thumbnail_url($post_id, 'full');
							if ($thumb) {
								echo '<image:image><image:loc>' . esc_url(normalize_url($thumb)) . '</image:loc></image:image>';
							}
						}

						// changefreq must match original logic exactly
						echo '<changefreq>' . esc_html($this->changefreq_by_id($post_id)) . '</changefreq>';

						// Keep date format identical
						echo '<lastmod>' . esc_html(get_the_modified_date('Y-m-d', $post_id)) . '</lastmod>';

						echo '<mobile:mobile/>';
						echo '</url>';
					}
				}

				echo '</urlset>';
				$output = ob_get_clean();
			}

			// Mirror original teardown behavior
			wp_reset_query();

			// Preserve filter + sanitization + ent2ncr + exit flow
			exit(ent2ncr($this->sanitize_output(apply_filters('minimal_sitemap', $output))));
		}

		public function sanitize_output($buffer) {
			$search  = ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s'];
			$replace = ['>', '<', '\\1'];
			return preg_replace($search, $replace, $buffer);
		}

		/**
		 * Return array of Polylang language slugs or false if not active.
		 */
		public function polylang() {
			// Using PLL() when available; avoid iterating nested structures manually.
			if (function_exists('PLL') && PLL()->model) {
				$langs = PLL()->model->get_languages_list();
				if (is_array($langs) && !empty($langs)) {
					$slugs = [];
					foreach ($langs as $lang) {
						if (isset($lang->slug)) {
							$slugs[] = $lang->slug;
						} elseif (is_array($lang) && isset($lang['slug'])) {
							$slugs[] = $lang['slug'];
						}
					}
					if (!empty($slugs)) {
						return $slugs;
					}
				}
			}
			return false;
		}

		/**
		 * Exact same logic as changefreq($post) but ID-based to avoid globals/loop state.
		 */
		private function changefreq_by_id($post_id) {
			$output = 'weekly';

			if (isset($_GET['changefreq'])) {
				$changefreq     = sanitize_text_field($_GET['changefreq']);
				$allowed_values = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
				if (in_array($changefreq, $allowed_values, true)) {
					return $changefreq;
				}
				// fall through to default logic if invalid
			}

			// Compute once to avoid duplicate calls
			$front_id = (int) get_option('page_on_front');
			$posts_id = (int) get_option('page_for_posts');
			$alt_id   = $this->polylang_alt_by_id($post_id);

			if ($post_id === $front_id || $post_id === $posts_id || $alt_id === $front_id || $alt_id === $posts_id) {
				$output = 'daily';
			} else {
				$post_type = get_post_type($post_id);
				if ($post_type === 'post') {
					$output = 'monthly';
				}
			}
			return $output;
		}

		/**
		 * Polylang alt for given post ID.
		 */
		private function polylang_alt_by_id($post_id) {
			if (function_exists('pll_get_post') && function_exists('pll_default_language')) {
				return (int) pll_get_post($post_id, pll_default_language());
			}
			return false;
		}
	}

	$sitemap = new minimal_sitemap();

	/**
	 * Activation hook to flush rules once when (if) used as a standalone plugin.
	 * If this lives in a theme, just re-save permalinks after adding the class.
	 */
	if (function_exists('register_activation_hook')) {
		register_activation_hook(__FILE__, function () {
			// Ensure rules are present before flushing
			$instance = new minimal_sitemap();
			$instance->add_rewrites();
			flush_rewrite_rules(false);
		});
		register_deactivation_hook(__FILE__, function () {
			flush_rewrite_rules(false);
		});
	}
}
?>
