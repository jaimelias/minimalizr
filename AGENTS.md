# AGENTS.md

## Description
`minimalizr` is the WordPress theme that provides the presentation layer and theme-level support for the `dynamicpackages` ecosystem.
It is a required theme dependency for `dynamicpackages` and supports integrations shared across the ecosystem.
The project uses PHP 8.1+, modern JavaScript, WordPress theme APIs, and jQuery Slim-compatible frontend code.
Prefer code that is easy to understand at a glance. Favor the smallest correct implementation that satisfies the requested behavior.
Simplicity must never come at the cost of security, correctness, frontend performance, accessibility, or backward compatibility.
---
## Ecosystem
`minimalizr` is part of a 4-component suite:
- `minimalizr`: the current WordPress theme and primary working repository.
- `dynamicpackages`: a WordPress plugin for service reservations.
- `dynamicaviation`: an optional WordPress plugin for private charter flight functionality.
- `dy-core`: the shared library consumed by ecosystem applications.
In this project, `dy-core` is installed under:
`submodules/dy-core`
---
## Dependency Boundaries
There are no direct application-level dependencies between `minimalizr`, `dynamicpackages`, and `dynamicaviation`.
They must never call each other's functions, classes, or methods directly.
Shared behavior belongs in `dy-core`.
Do not create direct dependencies such as:
`minimalizr -> dynamicpackages`
`minimalizr -> dynamicaviation`
`dynamicpackages -> minimalizr`
`dynamicaviation -> minimalizr`
The intended architecture is:
`minimalizr -> dy-core`
`dynamicpackages -> dy-core`
`dynamicaviation -> dy-core`
The theme may provide presentation and integration surfaces used by the ecosystem, but it must not reach directly into plugin internals.
If functionality is needed by more than one ecosystem application, the reusable behavior should live in `dy-core`.
---
## dy-core Ownership
The copy of `dy-core` inside this repository is installed under:
`submodules/dy-core`
This is a consumer copy of the shared library.
It is not the canonical editable source.
The canonical editable copy of `dy-core` lives in the `dynamicpackages` repository at:
`dynamicpackages/dy-core`
Do not modify `submodules/dy-core` as part of normal work in `minimalizr`.
If a requested change belongs in shared logic:
1. Identify that the appropriate implementation belongs in `dy-core`.
2. Do not duplicate the shared behavior inside the theme.
3. Do not edit the installed `submodules/dy-core` copy unless explicitly requested.
4. Mention that the canonical implementation must be changed from `dynamicpackages`.
5. Assume synchronization or publication into `minimalizr` is a separate manual release step.
Theme-specific rendering, template behavior, CSS, JavaScript, layout, and presentation logic should remain in `minimalizr`.
---
## Repository Scope
By default, edits must remain inside the `minimalizr` repository.
Reading sibling ecosystem projects is allowed when necessary to understand integrations, compatibility, or existing behavior.
Do not modify sibling projects unless explicitly requested.
Never modify:
- `vendor/`
- `node_modules/`
- `submodules/dy-core/` unless explicitly requested
- WordPress core files
- third-party generated dependencies
Do not extensively scan unrelated directories when a targeted search can answer the question.
---
## Think Before Coding
Do not start by changing code when the requested behavior or existing implementation has not been understood.
Before implementing:
- Inspect the relevant template, hook, helper, asset, or component first.
- Identify existing theme helpers, template parts, hooks, and conventions.
- Check whether reusable behavior already exists in `dy-core`.
- Do not assume undocumented behavior when it can be verified cheaply.
- Surface important assumptions.
- Surface meaningful tradeoffs when multiple valid implementations exist.
- Prefer the simpler implementation when it satisfies the same requirements.
- If an ambiguity materially affects correctness, identify it instead of silently choosing an interpretation.
- Do not invent requirements that were not requested.
When intended behavior can be reasonably inferred from existing code and the request, proceed with the smallest safe interpretation instead of blocking progress unnecessarily.
---
## Minimum Necessary Implementation
Implement the minimum code required to solve the requested problem correctly.
Do not add speculative functionality.
Do not add:
- features that were not requested
- abstractions for a single-use case without concrete reuse
- configuration options that were not requested
- unnecessary theme options
- unnecessary wrappers
- defensive code for impossible scenarios
- new dependencies without clear justification
Prefer a direct implementation over a generalized framework when both satisfy the requirement safely.
Do not optimize for hypothetical future requirements.
---
## Surgical Changes
Touch only what is necessary for the requested change.
When editing existing code:
- Do not improve unrelated templates.
- Do not reformat unrelated files.
- Do not rewrite unrelated comments.
- Do not rename unrelated selectors, classes, variables, functions, or methods.
- Do not refactor code merely because another style would be preferable.
- Match surrounding project conventions when they do not conflict with security or explicit project rules.
- If unrelated dead code is discovered, mention it rather than deleting it.
When the current change creates unused code, remove only imports, variables, functions, styles, selectors, or assets made obsolete by the current change.
Do not remove pre-existing dead code unless explicitly requested.
Keep functional changes separate from unrelated cleanup.
---
## General Code Style
Write code that is easy to read and understand at a glance.
Prefer:
- explicit intent
- simple control flow
- meaningful names
- small focused methods
- early returns
- guard clauses
- existing project abstractions
- predictable behavior
Avoid unnecessary cleverness.
### Exception Handling
Minimize `try { } catch (...) { }` usage.
Do not wrap code in `try/catch` merely as defensive programming.
Prefer, when appropriate:
- input validation
- guard clauses
- explicit precondition checks
- safe defaults
- WordPress error APIs
- checking return values
Use exceptions only when the called API genuinely communicates failures through exceptions or exception semantics are appropriate.
Do not silently swallow exceptions.
---
## PHP Requirements
All new or modified PHP code must be compatible with PHP 8.1 or newer.
Use modern PHP 8.1 syntax where appropriate.
Prefer:
- `[]` array syntax
- spread operators where they improve clarity
- parameter type declarations
- return type declarations
- property type declarations
- nullable and union types when semantically appropriate
- constructor property promotion when appropriate
- strict comparisons when the expected type is known
- explicit visibility on methods and properties
### Typing
New PHP functions and methods should be typed.
New or modified method parameters should use appropriate type declarations when compatible with WordPress and the existing public API.
New or modified methods should declare return types when compatible with WordPress and existing behavior.
New properties should be typed when their type is known.
Do not weaken types merely to silence static analysis.
Do not introduce native type declarations that break WordPress hooks, callbacks, template behavior, inheritance contracts, public APIs, or backward compatibility.
When WordPress passes broader or dynamic values, model those values accurately rather than forcing incorrect narrow types.
### Visibility and Static Methods
Every class method must use explicit visibility:
- `public`
- `protected`
- `private`
Every class property must use explicit visibility.
Use `private` by default for implementation details that do not need subclass access.
Use `protected` only when subclass access is intentional.
Use `public` only for the intended external API.
Declare methods `static` only when their behavior does not depend on instance state and static behavior fits the existing architecture.
### Control Flow
Prefer early returns and guard clauses over deeply nested conditionals when they improve readability.
Avoid unnecessary type juggling.
Keep functions and methods focused.
Search existing theme helpers and `dy-core` before creating new helpers.
---
## JavaScript Requirements
Write modern ES6+ JavaScript.
Prefer:
- `const` by default
- `let` when reassignment is required
- arrow functions where appropriate
- template literals when they improve readability
- destructuring when it improves clarity
- array methods such as `map`, `filter`, `find`, and `some` when appropriate
Do not use `var`.
Prefer arrow functions over traditional function expressions unless JavaScript semantics require dynamic `this`, `arguments`, constructor behavior, or another feature arrow functions do not provide.
### jQuery Slim Compatibility
JavaScript must remain compatible with jQuery Slim when jQuery is used.
Do not assume APIs excluded from jQuery Slim are available.
Do not introduce dependencies on jQuery Ajax or effects APIs when only jQuery Slim is guaranteed.
Prefer native browser APIs where appropriate.
For HTTP requests, prefer existing abstractions or `fetch()` when compatible with existing code.
### Frontend Performance
Frontend performance is especially important in `minimalizr`.
Avoid render-blocking patterns.
Do not introduce unnecessary synchronous work during initial rendering.
Prefer:
- deferred or footer-loaded scripts when appropriate
- event-driven initialization
- loading assets only where needed
- scoped DOM queries
- minimizing repeated DOM access
- avoiding unnecessary layout recalculation
- native lazy-loading APIs where appropriate
- WordPress enqueue APIs
- conditional asset loading
Do not enqueue assets globally when they are only needed on a specific template or feature unless the existing architecture intentionally does so.
Do not move code asynchronously when execution order is required for correctness.
---
## Theme Development Rules
`minimalizr` is a WordPress theme.
Use WordPress theme APIs and template conventions appropriately.
Preserve existing:
- theme hooks
- template hierarchy behavior
- template part contracts
- menus
- widget areas
- theme supports
- image sizes
- customizer or theme settings
- CSS selectors used by JavaScript
- public JavaScript events
- asset handles
- localization domains
- integration hooks
unless the requested task explicitly requires changing them.
Do not place reusable application business logic in theme templates.
Templates should focus primarily on presentation and orchestration.
Move reusable shared ecosystem logic to `dy-core` when appropriate.
Theme-specific presentation helpers may remain in `minimalizr`.
Avoid performing expensive database queries directly inside templates when an existing helper or earlier data-loading stage can provide the information.
---
## HTML and Output
Generate valid semantic HTML where practical.
Prefer meaningful HTML elements over unnecessary generic wrappers.
Preserve accessibility behavior.
When output contains dynamic values:
- escape text with the appropriate WordPress escaping function
- escape attributes appropriately
- escape URLs appropriately
- sanitize trusted HTML according to the intended allowed markup
Do not disable escaping merely to simplify templates.
Do not introduce invalid nested interactive elements.
Preserve existing ARIA relationships and labels unless intentionally changing accessibility behavior.
---
## CSS and Markup Discipline
Do not rename existing CSS classes or DOM identifiers unless the task explicitly requires it.
Before modifying a selector, inspect its usage in:
- PHP templates
- JavaScript
- CSS
- ecosystem integrations when relevant
Do not create highly specific selectors when a simpler existing convention can be reused.
Avoid `!important` unless required by an existing architecture or a concrete specificity constraint.
Do not introduce global CSS when a component-scoped rule is sufficient.
Avoid layout shifts introduced by unnecessary late-loading dimensions or DOM manipulation.
---
## WordPress Development Rules
Use WordPress APIs instead of duplicating functionality already provided by WordPress.
Preserve existing:
- hooks
- filters
- public method signatures
- public function signatures
- theme supports
- option names
- metadata keys
- template contracts
- REST routes
- AJAX actions
- shortcodes
- asset handles
- external contracts
unless the task explicitly requires changing them.
When handling external input, use appropriate validation and sanitization.
Escape output at the point of output.
For privileged operations, verify capabilities and nonces where applicable.
Use `$wpdb->prepare()` or appropriate WordPress database APIs when SQL contains dynamic values.
Do not disable security checks merely to make automated validation pass.
---
## WordPress Core Reference
Do not scan `wp-admin` or `wp-includes` extensively.
For questions about WordPress functions, classes, methods, hooks, filters, parameters, return values, or theme APIs, consult:
`https://developer.wordpress.org/reference/`
Use local WordPress core only when necessary to verify behavior specific to the installed version.
When inspecting local core:
- locate the relevant symbol first
- read only the relevant implementation
- avoid loading entire core files unnecessarily
- avoid broad recursive scans
---
## Polylang
Polylang is an optional dependency.
`minimalizr` must not assume Polylang is installed or active unless the relevant feature explicitly requires it.
Code integrating with Polylang must degrade safely when Polylang is unavailable.
For Polylang functions, consult:
`https://polylang.pro/documentation/support/developers/function-reference/`
For Polylang filters, consult:
`https://polylang.pro/documentation/support/developers/filter-reference/`
Prefer official Polylang documentation before inspecting plugin source code.
When local Polylang source inspection is necessary:
- locate the relevant symbol first
- inspect only the relevant implementation
- avoid scanning the entire plugin
- do not modify Polylang source
---
## Available CLI Tools
The project provides the following primary development tools:
- WP-CLI: `wp`
- Composer: `composer`
- PHPStan: `vendor/bin/phpstan`
- PHP_CodeSniffer: `vendor/bin/phpcs`
- PHP Code Beautifier and Fixer: `vendor/bin/phpcbf`
- PHP Parallel Lint: `vendor/bin/parallel-lint`
Prefer Composer scripts when an equivalent project command exists.
Prefer:
`composer phpstan`
over:
`vendor/bin/phpstan analyse`
Prefer:
`composer check`
for the complete standard PHP validation workflow.
---
## PHP Syntax Validation
After modifying PHP code, run:
`composer lint`
Syntax errors introduced by a change must always be fixed before the task is considered complete.
Syntax validation should normally be the first validation performed after PHP changes.
---
## PHPStan
PHPStan is the primary static analysis tool.
The project uses `szepeviktor/phpstan-wordpress` so PHPStan understands WordPress APIs and WordPress-specific dynamic behavior.
The PHPStan configuration is stored in:
`phpstan.neon.dist`
Run:
`composer phpstan`
after modifying PHP code.
The installed `submodules/dy-core` directory may be available for symbol resolution when required, but it is not an editable source directory for normal `minimalizr` tasks.
New PHPStan errors introduced by a change must be fixed.
Do not:
- add broad `ignoreErrors`
- introduce unnecessary baselines
- suppress errors merely to make PHPStan pass
- weaken PHPStan configuration to hide a new issue
Prefer fixing the underlying type or control-flow problem.
---
## WordPress Coding Standards
PHP_CodeSniffer with WordPress Coding Standards is used for WordPress-specific code quality checks.
The PHPCS configuration is stored in:
`phpcs.xml.dist`
Run:
`composer phpcs`
after modifying PHP code.
The project uses:
- `WordPress-Core`
- `WordPress-Extra`
Do not mechanically suppress PHPCS rules merely to make validation pass.
### Automatic Fixes
PHP Code Beautifier and Fixer is available through:
`composer phpcbf`
Do not run project-wide automatic fixes without understanding their scope.
Prefer targeted fixes to affected files.
Avoid unrelated formatting diffs.
---
## Composer
Composer manages development tooling and PHP dependencies.
Do not manually modify files inside:
`vendor/`
When `composer.json` is modified, run:
`composer validate`
When dependencies are added, removed, or updated, also run:
`composer audit`
Commit `composer.lock` when dependency changes intentionally modify it.
Do not update unrelated dependencies as part of a focused task.
---
## Validation Workflow
For PHP changes, use the following validation order:
1. PHP syntax validation
2. PHPStan
3. WordPress Coding Standards
The standard commands are:
`composer lint`
`composer phpstan`
`composer phpcs`
For substantial PHP changes or before completing a task, prefer:
`composer check`
When iterating on a specific problem, the smallest relevant validation command may be executed first.
Before completing the task, all validation relevant to the change should pass.
---
## Validation Failures
Do not report a task as successfully completed when a relevant validation command is failing because of the current change.
If validation exposes an existing unrelated problem, distinguish clearly between:
- failures introduced by the current change
- failures that already existed
Do not fix unrelated existing failures unless they prevent completion of the requested task or the user explicitly asks for them to be fixed.
---
## Change Discipline
Keep changes scoped to the requested task.
Do not perform unrelated refactors while implementing a focused feature or bug fix.
Before introducing a new:
- class
- helper
- trait
- abstraction
- utility
- Composer dependency
search the existing theme code and `dy-core` for equivalent functionality.
Prefer extending existing architecture over building parallel implementations.
Preserve backward compatibility unless the requested change explicitly requires breaking it.
Do not modify `submodules/dy-core` automatically.
---
## Completion Criteria
For PHP work, a task is normally complete when:
- the requested behavior has been implemented
- only necessary files and code were changed
- existing public and theme behavior has been preserved unless intentionally changed
- PHP syntax validation passes
- PHPStan passes for the affected code
- PHPCS passes or remaining findings are known pre-existing issues
- no unnecessary abstractions or dependencies were introduced
- no unrelated cleanup was included
The standard final validation command is:
`composer check`
When reporting completion, state which validation commands were actually executed and their result.
If validation could not be executed, state that explicitly instead of implying that it passed.
If the requested change belongs in `dy-core`, explicitly state that the canonical implementation must be changed in `dynamicpackages/dy-core` and later published into this project's `submodules/dy-core`.
