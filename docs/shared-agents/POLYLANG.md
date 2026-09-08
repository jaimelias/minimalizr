# POLYLANG.md

# Polylang Instructions
Apply these instructions only when a task involves Polylang functions, filters, translations, language switching, multilingual behavior, or Polylang-specific integration.
Polylang is an optional dependency.
Do not assume Polylang is installed or active unless the relevant feature explicitly requires it.
Code integrating with Polylang must degrade safely when Polylang is unavailable.
Before using Polylang functions or APIs, verify availability where necessary.
For Polylang functions, consult:
`https://polylang.pro/documentation/support/developers/function-reference/`
For Polylang filters, consult:
`https://polylang.pro/documentation/support/developers/filter-reference/`
Prefer official Polylang developer documentation before inspecting plugin source code.
If local Polylang source inspection is necessary:
- locate the relevant symbol first
- inspect only the relevant implementation
- avoid scanning the entire plugin
- do not modify Polylang source
