---
name: php-safe-change
description: Safely implement or debug a classic PHP feature in the Sobhan website while preserving routes, authentication, permissions, CSRF, RTL UI, and backward compatibility.
---

# PHP Safe Change

Use for PHP bug fixes, page changes, form actions, AJAX endpoints, or small refactors.

## Workflow

1. Read the target file and all required includes.
2. Trace the request from route to write/read query.
3. Locate existing auth, permission, CSRF, DB, layout, logging, and flash helpers.
4. Search for duplicated route or legacy behavior.
5. Define the smallest safe patch.
6. Implement without changing unrelated architecture.
7. Run PHP lint on changed files.
8. Review authorization, input validation, output escaping, and error paths.
9. Report what was not tested live.

## Prohibited

- Framework rewrite
- Raw SQL errors in UI
- Permission checks only in menu
- Silent destructive behavior
- Replacing existing helpers with parallel utilities without need
