# AGENTS.md

`minimalizr`: WordPress theme providing presentation and theme-level support for the `dynamicpackages` ecosystem.

- Before coding, read `../../plugins/dynamicpackages/dy-core/docs/CODING.md`; its rules are mandatory project-wide. Resolve documentation links relative to their containing file; project paths start at this repository root.
- `submodules/dy-core/` is this project's shared library dependency. Never modify or delete its contents.
- Prefer targeted `rg` searches over recursive file reads

## Common Entry Points

- Main theme bootstrap: `functions.php`
- Canonical shared library: `submodules/dy-core/`
- Shared helpers: `inc/`
- Main template: `index.php`
- Page template: `page.php`
- Single post template: `single.php`
- Archives template: 'archive.php'
- Header Template: `header.php`
- Footer Template: `footer.php`
- Library of templates: `template-parts`

