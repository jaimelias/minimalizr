# WORDPRESS.md

# WordPress Instructions
Apply these instructions when work involves WordPress APIs, hooks, filters, REST, AJAX, cron, options, metadata, database access, templates, capabilities, sanitization, escaping, shortcodes, or asset registration/enqueueing.
## Development Rules
Use WordPress APIs instead of duplicating functionality already provided by WordPress.
Preserve existing contracts unless the requested task explicitly requires changing them, including:
- hooks
- filters
- public method signatures
- public function signatures
- option names
- metadata keys
- database schemas
- REST routes
- AJAX actions
- cron hooks
- shortcodes
- asset handles
- localization domains
- external and integration contracts
When handling external input, use appropriate validation and sanitization.
Escape output at the point of output using the appropriate WordPress escaping function.
For privileged operations, verify user capabilities and nonces where applicable.
Use `$wpdb->prepare()` or appropriate WordPress database APIs when SQL contains dynamic values.
Do not disable, bypass, or suppress security checks merely to make automated validation pass.
## WordPress Core Reference
Do not scan `wp-admin` or `wp-includes` extensively.
For questions about WordPress functions, classes, methods, hooks, filters, parameters, return values, or theme APIs, consult the official WordPress Developer Reference first:
`https://developer.wordpress.org/reference/`
Use local WordPress core only when necessary to verify implementation details or behavior specific to the installed version.
When inspecting local core:
- locate the relevant symbol first
- read only the relevant function, class, or nearby implementation
- avoid loading entire core files when a smaller section is sufficient
- avoid broad recursive scans
Do not load large sections of WordPress documentation or WordPress core into context when a targeted reference is sufficient.
