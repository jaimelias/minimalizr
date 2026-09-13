# `rg` for Codex

Use `rg` first. Search narrowly before reading files.

Always exclude `vendor`, `node_modules`, and `.git` with:

```bash
-g '!vendor/**' -g '!node_modules/**' -g '!.git/**'
```

```bash
# Find files
rg --files | rg 'name'

# Search text
rg -n 'pattern'

# Exact text
rg -n -F 'foo()'

# PHP / JS
rg -n 'pattern' -t php
rg -n 'pattern' -t js

# Specific paths
rg -n 'pattern' src/ includes/ dy-core/

# Context
rg -n -C 5 'pattern' file.php

# Matching files only
rg -l 'pattern'

# Ignore case
rg -i 'pattern'

# Whole word
rg -w 'ClassName'

# Globs
rg 'pattern' -g '*.php'

# Hidden files
rg --hidden 'pattern'
```

## PHP / WordPress

```bash
# Class/function definitions
rg -n 'class\s+Name|function\s+name' -t php

# Method/function call
rg -n -F 'name(' -t php

# WordPress hooks
rg -n 'add_(action|filter)\(' -t php
rg -n -F "add_action('init'" -t php

# Debug leftovers
rg -n 'var_dump|print_r|error_log' -t php
```

## JavaScript / jQuery

```bash
# Function/reference
rg -n 'name|=>|function' -t js

# jQuery
rg -n '\$\(|jQuery\(' -t js

# Events/AJAX
rg -n '\.on\(|\.ajax\(|fetch\(' -t js

# Debug leftovers
rg -n 'console\.log' -t js
```

## Codex rule

**Search → narrow → inspect → edit.**


Prefer targeted `rg` searches over recursive file reads.

Fallback if `rg` is unavailable:

```bash
git grep -n 'pattern'
```
