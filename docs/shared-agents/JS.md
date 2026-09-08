# JS.md

# JavaScript Instructions
Apply these instructions when reading or modifying JavaScript implementation code.
## Language
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
## jQuery Slim Compatibility
JavaScript must remain compatible with jQuery Slim when jQuery is used.
Do not assume APIs excluded from jQuery Slim are available.
Do not introduce dependencies on jQuery Ajax or jQuery effects APIs when only jQuery Slim is guaranteed.
Prefer native browser APIs where appropriate.
For HTTP requests, prefer existing project abstractions or `fetch()` when compatible with the existing implementation.
## Frontend Performance
Avoid render-blocking patterns.
Do not introduce unnecessary synchronous frontend work during initial page rendering.
Prefer:
- deferred execution when appropriate
- event-driven initialization
- loading code only where needed
- DOM queries scoped to the relevant component
- avoiding repeated DOM lookups inside loops
- avoiding unnecessary layout recalculations
- WordPress enqueue APIs for assets
Do not move code asynchronously when execution order is required for correctness.
