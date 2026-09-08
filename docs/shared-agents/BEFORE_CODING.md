# BEFORE_CODING.md

# Before Coding
Use these instructions before making implementation changes.
## Understand Before Editing
Do not start by changing code until the relevant behavior and existing implementation are understood.
Before implementing:
- Inspect the relevant existing code first.
- Identify existing helpers, abstractions, hooks, template parts, components, and conventions that already solve part of the problem.
- Follow the project’s `dy-core` ownership rules before introducing reusable ecosystem behavior.
- Do not assume undocumented behavior when it can be verified cheaply.
- Surface important assumptions.
- Surface meaningful tradeoffs when more than one valid implementation exists.
- Prefer the simpler implementation when it satisfies the same requirements.
- If an ambiguity materially affects correctness, identify it rather than silently choosing an interpretation.
- Do not invent requirements that were not requested.
When intended behavior can be reasonably inferred from the existing code and request, proceed with the smallest safe interpretation instead of blocking progress unnecessarily.
## Minimum Necessary Implementation
Implement the minimum code required to solve the requested problem correctly.
Do not add speculative functionality.
Do not add:
- features that were not requested
- abstractions for a single-use case without a concrete reuse need
- configuration options that were not requested
- unnecessary extensibility
- unnecessary wrappers
- defensive code for scenarios that cannot reasonably occur
- new dependencies without clear justification
Prefer a direct implementation over a generalized framework when both satisfy the requirement safely and maintainably.
Do not optimize for hypothetical future requirements.
## Surgical Changes
Touch only what is necessary for the requested change.
When editing existing code:
- Do not improve unrelated code.
- Do not reformat unrelated sections or files.
- Do not rewrite unrelated comments.
- Do not rename unrelated selectors, variables, functions, classes, or methods.
- Do not refactor code merely because another style would be preferable.
- Match surrounding project conventions when they do not conflict with security or explicit project rules.
- If unrelated dead code is discovered, mention it rather than deleting it.
When the current change makes something unused, remove only imports, variables, functions, methods, styles, selectors, or assets made obsolete by the current change.
Do not remove pre-existing dead code unless explicitly requested.
Keep functional changes separate from unrelated cleanup.
## Change Discipline
Before introducing a new class, helper, trait, abstraction, utility, or dependency, search the existing codebase and follow the project’s shared-code ownership rules.
Prefer extending existing architecture over building parallel implementations.
Preserve backward compatibility unless the requested change explicitly requires breaking it.
