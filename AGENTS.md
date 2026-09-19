# AGENTS.md

`minimalizr`: WordPress theme providing presentation and theme-level support for the `dynamicpackages` ecosystem.

- Use `.codex/map.txt` to locate relevant code before searching the repository.
- Prefer targeted `rg` searches over recursive file reads, read `submodules/dy-core/docs/RG.md`.
- Before coding, read `submodules/dy-core/docs/CODING.md`; its rules are mandatory project-wide. Resolve documentation links relative to their containing file; project paths start at this repository root.
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

## DEV Tools
- Local site: `http://localhost:8888/wordpress`
- Read the project's `composer.json` for tools and configuration.
- Runtime: PHP 8.1 (Apache)
- Diagnostics: `Query Monitor` is installed; use it when investigating runtime notices, queries, and asset dependencies.
