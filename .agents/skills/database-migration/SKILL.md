---
name: database-migration
description: Design or review an idempotent MySQL/MariaDB schema repair, migration, or seed for the Sobhan PHP platform without losing existing data.
---

# Database Migration

## Required Inputs

- Current schema evidence
- Desired schema
- Stable business keys
- Affected code paths
- Expected rerun behavior

## Workflow

1. Inspect current migrations/installers/schema.
2. Verify table and column names from code or DB evidence.
3. Separate schema changes from seed data.
4. Use existence checks.
5. Preserve rows and null/default compatibility.
6. Add unique keys to enforce idempotency when safe.
7. Use transactions when supported.
8. Document backup and rollback.
9. Validate rerun behavior.

Never use `DROP` or `TRUNCATE` unless explicitly approved.
