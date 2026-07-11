---
name: release-validation
description: Validate a Sobhan website change before deployment using diff review, PHP lint, permission checks, database safety, targeted regression checks, backup, and rollback notes.
---

# Release Validation

## Checklist

1. Git diff reviewed.
2. Unrelated changes excluded.
3. Changed PHP files linted.
4. Auth/permission/scope reviewed.
5. CSRF/input/output reviewed.
6. Database migration idempotency reviewed.
7. Affected role flows tested.
8. Persian RTL and dates checked.
9. Logs checked.
10. Backup and rollback documented.

Do not state production readiness when live database, runtime, or role flows were unavailable.
