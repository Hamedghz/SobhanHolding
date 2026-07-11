---
name: import-pipeline
description: Implement or review Sobhan Excel/CSV/API data imports using staging, normalized headers, column mapping, validation, batch history, error reporting, and idempotent upsert.
---

# Import Pipeline

## Required Flow

`Source -> batch -> staging -> normalize -> map -> validate -> preview -> commit/upsert -> summary`

## Rules

- File name is not the only detector.
- Normalize spaces, Persian/Arabic digits, and known header variants.
- Validate required headers.
- Keep row-level errors.
- Do not partially commit without explicit design.
- Use stable upsert keys.
- Record inserted, updated, skipped, and failed counts.
- Protect large uploads and memory use.
- Keep uploaded files non-executable.
