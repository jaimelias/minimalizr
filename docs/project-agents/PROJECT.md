# PROJECT

## Project Role
`minimalizr` is the WordPress theme that provides the presentation layer and theme-level support for the `dynamicpackages` ecosystem.
It is a required theme dependency for `dynamicpackages` and supports integrations shared across the ecosystem.
The project uses PHP 8.1+, modern JavaScript, WordPress theme APIs, and jQuery Slim-compatible frontend code.
Prefer code that is easy to understand at a glance. Favor the smallest correct implementation that satisfies the requested behavior. Simplicity must never come at the cost of security, correctness, frontend performance, accessibility, or backward compatibility.
## Ecosystem
`minimalizr` is part of a four-component suite:
- `minimalizr`: the current WordPress theme and primary working repository.
- `dynamicpackages`: a WordPress plugin for service reservations.
- `dynamicaviation`: an optional WordPress plugin for private charter flight functionality.
- `dy-core`: the shared library consumed by ecosystem applications.
In this project, `dy-core` is installed under:
`submodules/dy-core`
## Dependency Boundaries
There are no direct application-level dependencies between `minimalizr`, `dynamicpackages`, and `dynamicaviation`.
They must never call each other's functions, classes, or methods directly.
Shared behavior belongs in `dy-core`.
Do not create direct dependencies such as:
- `minimalizr -> dynamicpackages`
- `minimalizr -> dynamicaviation`
- `dynamicpackages -> minimalizr`
- `dynamicaviation -> minimalizr`
The intended architecture is:
- `minimalizr -> dy-core`
- `dynamicpackages -> dy-core`
- `dynamicaviation -> dy-core`
The theme may provide presentation and integration surfaces used by the ecosystem, but it must not reach directly into plugin internals.
If functionality is needed by more than one ecosystem application, reusable behavior should live in `dy-core`.
## dy-core Ownership
The copy of `dy-core` under `submodules/dy-core` is a consumer copy. It is not the canonical editable source.
The canonical editable copy lives at:
`dynamicpackages/dy-core`
Do not modify `submodules/dy-core` as part of normal work in `minimalizr`.
If a requested change belongs in shared logic:
1. Identify that the implementation belongs in `dy-core`.
2. Do not duplicate the shared behavior inside the theme.
3. Do not edit `submodules/dy-core` unless explicitly requested.
4. State that the canonical implementation must be changed from `dynamicpackages`.
5. Treat synchronization or publication into this repository as a separate manual release step.
Theme-specific rendering, templates, CSS, JavaScript, layout, and presentation logic should remain in `minimalizr`.
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
## Theme Development Rules
Use WordPress theme APIs and template conventions appropriately.
Preserve existing:
- theme hooks
- template hierarchy behavior
- template-part contracts
- menus
- widget areas
- theme supports
- image sizes
- Customizer or theme settings
- CSS selectors used by JavaScript
- public JavaScript events
- asset handles
- localization domains
- integration hooks
unless the requested task explicitly requires changing them.
Do not place reusable application business logic in theme templates.
Templates should focus primarily on presentation and orchestration.
Move reusable shared ecosystem logic to `dy-core` when appropriate. Theme-specific presentation helpers may remain in `minimalizr`.
Avoid expensive database queries directly inside templates when an existing helper or earlier data-loading stage can provide the information.
## HTML and Output
Generate valid semantic HTML where practical.
Preserve accessibility behavior.
When output contains dynamic values:
- escape text with the appropriate WordPress escaping function
- escape attributes appropriately
- escape URLs appropriately
- sanitize trusted HTML according to the intended allowed markup
Do not disable escaping merely to simplify templates.
Do not introduce invalid nested interactive elements.
Preserve existing ARIA relationships and labels unless intentionally changing accessibility behavior.
## CSS and Markup Discipline
Do not rename existing CSS classes or DOM identifiers unless the task explicitly requires it.
Before modifying a selector, inspect its usage in PHP templates, JavaScript, CSS, and ecosystem integrations when relevant.
Do not create highly specific selectors when a simpler existing convention can be reused.
Avoid `!important` unless required by the existing architecture or a concrete specificity constraint.
Do not introduce global CSS when a component-scoped rule is sufficient.
Avoid layout shifts introduced by unnecessary late-loading dimensions or DOM manipulation.
## Frontend Performance
Frontend performance is especially important in `minimalizr`.
Avoid render-blocking patterns and unnecessary synchronous work during initial rendering.
Prefer deferred or footer-loaded scripts when appropriate, event-driven initialization, conditional asset loading, scoped DOM queries, and native lazy-loading APIs where suitable.
Do not enqueue assets globally when they are needed only on a specific template or feature unless the existing architecture intentionally does so.
Do not move code asynchronously when execution order is required for correctness.
## Static Analysis Scope
The installed `submodules/dy-core` directory may be available for PHPStan symbol resolution when required, but it is not an editable source directory for normal `minimalizr` tasks.
## Completion Notes
If the requested change belongs in `dy-core`, explicitly state that the canonical implementation must be changed in `dynamicpackages/dy-core` and later published into this project's `submodules/dy-core`.
