# AGENTS.md

## Dev environment tips
- The folder `./submodules` is a top level library, it can not be edited from this project.
- This theme's git repository is `https://github.com/jaimelias/minimalizr`
- This theme is compatible with repository (plugin) `https://github.com/jaimelias/dynamicpackages`
- This theme is compatible with repository (plugin) `https://github.com/jaimelias/dynamicaviation`

## Testing instructions
- Project directory `/Applications/MAMP/htdocs/wordpress`.
- WP-CLI available at `/Applications/MAMP/htdocs/wordpress` e.g. `wp --version`.
- PHP available at `/Applications/MAMP/htdocs/wordpress` e.g. `php --version`.
- Run `/Applications/MAMP/bin/startApache.sh && /Applications/MAMP/bin/startMysql.sh` to start MAMP.
- HTTP Wordpress installation is available at `http://localhost:8888/wordpress`.
- HTTP "wp-json" endpoint is available at `http://localhost:8888/wordpress/wp-json`.

## Code style
- Javascript code should be es6 optimized and compatible with Jquery Slim.
- PHP code suggestions/fixes/optimizations/changes should always be typed.
- Suggestions should include valid PHP 8.1 code with modern syntax.
- Before writing optimized code, analyze the return contract (types, values, exceptions, side effects) of the original method/function, then compare it against the optimized implementation to confirm equivalence — explicitly flag any behavioral regressions, edge-case differences, or contract changes (e.g., altered return types, error handling, or nullability) introduced by the optimization.