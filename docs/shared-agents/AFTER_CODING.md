# AFTER_CODING.md

# After Coding
Use these instructions after modifying code and before reporting the task complete.
## Available CLI Tools
The standard PHP development tools are:
- WP-CLI: `wp`
- Composer: `composer`
- PHPStan: `vendor/bin/phpstan`
- PHP_CodeSniffer: `vendor/bin/phpcs`
- PHP Code Beautifier and Fixer: `vendor/bin/phpcbf`
- PHP Parallel Lint: `vendor/bin/parallel-lint`
Prefer Composer scripts when an equivalent project command exists.
Prefer `composer phpstan` over invoking `vendor/bin/phpstan analyse` directly.
Prefer `composer check` for the complete standard PHP validation workflow.
## Validation Scope
Run validation relevant to the files and behavior changed.
For PHP changes, use this standard order:
1. PHP syntax validation
2. PHPStan
3. WordPress Coding Standards
The standard commands are:
```bash
composer lint
composer phpstan
composer phpcs
```
For substantial PHP changes or before completing a PHP task, prefer:
```bash
composer check
```
When iterating on a specific problem, the smallest relevant validation command may be executed first. Before completion, all validation relevant to the change should pass.
Follow any repository-specific analysis-scope notes in `docs/project-agents/`.
## PHP Syntax Validation
After modifying PHP code, run:
```bash
composer lint
```
Syntax errors introduced by a change must always be fixed before the task is considered complete.
Syntax validation should normally be the first validation performed after PHP changes.
## PHPStan
PHPStan is the primary static analysis tool.
The project uses `szepeviktor/phpstan-wordpress` so PHPStan can understand WordPress-specific APIs and dynamic behavior.
The PHPStan configuration is stored in:
`phpstan.neon.dist`
After modifying PHP code, run:
```bash
composer phpstan
```
New PHPStan errors introduced by the change must be fixed.
Do not:
- add broad `ignoreErrors` rules
- introduce unnecessary baselines
- suppress errors merely to make PHPStan pass
- weaken PHPStan configuration to hide a new problem
Prefer fixing the underlying type or control-flow issue.
If a narrow suppression is truly necessary because PHPStan cannot accurately model valid behavior, keep it targeted and provide a clear technical justification.
## WordPress Coding Standards
PHP_CodeSniffer with WordPress Coding Standards is used for WordPress-specific code quality checks.
The PHPCS configuration is stored in:
`phpcs.xml.dist`
After modifying PHP code, run:
```bash
composer phpcs
```
The project uses:
- `WordPress-Core`
- `WordPress-Extra`
Do not mechanically suppress PHPCS rules merely to make validation pass.
### Automatic Fixes
PHP Code Beautifier and Fixer is available through:
```bash
composer phpcbf
```
Do not run project-wide automatic fixes without understanding the resulting scope.
Prefer targeted fixes to affected files and avoid unrelated formatting diffs.
## Composer
Do not manually modify files inside `vendor/`.
When `composer.json` is modified, run:
```bash
composer validate
```
When dependencies are added, removed, or updated, also run:
```bash
composer audit
```
Commit `composer.lock` when dependency changes intentionally modify it.
Do not update unrelated dependencies as part of a focused task.
## Validation Failures
Do not report a task as successfully completed when a relevant validation command is failing because of the current change.
If validation exposes an existing unrelated problem, clearly distinguish:
- failures introduced by the current change
- failures that already existed
Do not fix unrelated existing failures unless they prevent completion of the requested task or the user explicitly asks for them to be fixed.
Do not modify unrelated code merely to reduce a global validation count.
## Completion Criteria
A code task is normally complete when:
- the requested behavior has been implemented
- only necessary files and code were changed
- existing public behavior has been preserved unless intentionally changed
- relevant syntax validation passes
- PHPStan passes for affected PHP code
- PHPCS passes or remaining findings are known pre-existing issues
- no unnecessary abstractions or dependencies were introduced
- no unrelated cleanup was included
When reporting completion, state which validation commands were actually executed and their results.
If validation could not be executed, state that explicitly instead of implying that it passed.
Follow repository-specific completion notes in `docs/project-agents/`.
