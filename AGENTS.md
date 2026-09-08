# AGENTS.md

## Project
`minimalizr` is the WordPress theme that provides the presentation layer and theme-level support for the `dynamicpackages` ecosystem.
The authoritative repository-specific architecture, scope, ownership, and domain rules are in:
`docs/project-agents/minimalizr.md`
Read that file before making code changes or architectural decisions in this repository.
## Instruction Loading
Use progressive disclosure. Read only the instruction files relevant to the current task; do not load every shared document by default.
Before making implementation changes, read:
`docs/shared-agents/BEFORE_CODING.md`
Before modifying PHP code, also read:
`docs/shared-agents/PHP.md`
Before modifying JavaScript code, also read:
`docs/shared-agents/JS.md`
For work involving WordPress APIs, hooks, filters, REST, AJAX, cron, options, metadata, database access, templates, capabilities, sanitization, escaping, shortcodes, or asset registration/enqueueing, also read:
`docs/shared-agents/WORDPRESS.md`
For Polylang-related work, also read:
`docs/shared-agents/POLYLANG.md`
After modifying code and before reporting completion, read:
`docs/shared-agents/AFTER_CODING.md`
## Instruction Priority
Follow the explicit task request together with these repository instructions. Where these repository documents overlap or conflict:
1. This `AGENTS.md` defines repository-wide routing and mandatory constraints.
2. `docs/project-agents/minimalizr.md` controls project-specific architecture, ownership, domain, and repository-scope rules.
3. Relevant files under `docs/shared-agents/` provide language, framework, workflow, and validation rules.
A task request may activate an exception only where these instructions explicitly allow an exception when requested. Do not treat generic shared guidance as permission to violate a project-specific constraint.
## Core Constraints
- Work inside this repository by default.
- Inspect the existing implementation before changing behavior.
- Implement the smallest correct change.
- Do not perform unrelated refactors, formatting, renames, or cleanup.
- Preserve backward compatibility and public contracts unless the task explicitly requires changing them.
- Search for existing helpers and abstractions before introducing new ones.
- Follow the project-specific `dy-core` ownership rules for shared ecosystem behavior.
- Do not create direct application-level dependencies between `dynamicpackages`, `dynamicaviation`, and `minimalizr`.
- Never modify `vendor/`, `node_modules/`, WordPress core, or third-party generated dependencies.
- Do not modify sibling repositories unless explicitly requested.
- Do not weaken security or validation rules merely to make automated checks pass.
- Do not claim validation passed unless the relevant commands were actually executed successfully.
