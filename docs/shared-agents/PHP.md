# PHP.md

# PHP Instructions
Apply these instructions when reading or modifying PHP implementation code.
## Requirements
All new or modified PHP code must be compatible with PHP 8.1 or newer.
Use modern PHP 8.1 syntax where appropriate.
Prefer:
- `[]` array syntax
- spread operators where they improve clarity
- parameter type declarations
- return type declarations
- property type declarations
- nullable and union types when semantically appropriate
- constructor property promotion when it improves the implementation
- strict comparisons when the expected type is known
- explicit visibility on methods and properties
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
Avoid unnecessary cleverness and unnecessary type juggling.
## Typing
New PHP functions and methods should be typed.
New or modified method parameters should use appropriate type declarations when compatible with WordPress, the surrounding API, and existing public behavior.
New or modified methods should declare return types when compatible with WordPress, inheritance contracts, callbacks, and existing behavior.
New properties should be typed when their type is known.
Do not remove useful type information or weaken types merely to silence static analysis.
Do not introduce native type declarations that break WordPress hooks, callbacks, inheritance contracts, public APIs, template behavior, external integrations, or supported backward compatibility.
When WordPress or an external API returns broader or dynamic values, model those values accurately rather than forcing incorrect narrow types.
## Visibility and Static Methods
Every class method and property must use explicit visibility.
Use `private` by default for implementation details that do not need subclass access.
Use `protected` only when subclass access is intentional.
Use `public` only for the intended external API.
Declare methods `static` only when their behavior does not depend on instance state and static behavior fits the existing architecture.
Do not make methods static merely as a stylistic preference.
## Control Flow
Prefer early returns and guard clauses over deeply nested conditionals when they improve readability.
Keep functions and methods focused.
Search for existing helpers before creating new ones, and follow the project instructions when logic may belong in shared code.
## Exception Handling
Minimize `try { } catch (...) { }` usage.
Do not wrap code in `try/catch` merely as defensive programming.
Prefer, when appropriate:
- input validation
- guard clauses
- explicit precondition checks
- safe defaults
- WordPress error APIs
- checking return values
Use exceptions when the called API genuinely communicates failures through exceptions or when exception semantics are appropriate for the domain.
Do not silently swallow exceptions.
If an exception is caught, there must be a concrete reason for handling it at that layer.
