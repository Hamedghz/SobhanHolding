# Sobhan Database Reference

## Rules

- Verify actual schema before coding.
- Use idempotent migrations.
- Preserve data.
- Separate schema from seed content.
- Use stable unique business keys.
- Use transactions for multi-table writes.
- Record import batch and source information.
- Maintain historical reproducibility for KPI and commission outputs.

## Suggested Data Domains

Names are architectural suggestions, not guaranteed existing tables:

- employees / users
- roles / permissions
- organizational_units
- sales_lines
- sales_team_members
- sales_regions / routes
- sales_aggregate_rows
- inventory_aggregate_rows
- sales_targets
- customer_class_coefficients
- product_priorities
- commission_formula_settings
- import_batches
- import_row_errors
- audit_logs
- integration_logs

## Import Requirements

Every import should track:

- batch ID
- source type
- source filename or endpoint
- period/range
- uploaded/requested by
- started/finished timestamps
- total/valid/invalid/inserted/updated/skipped counts
- checksum where practical
- status
- sanitized error summary

## Reporting Requirements

Document:

- Grain of each view
- Date field used
- Return/cancellation handling
- Gross/net/discount/tax definitions
- Scope keys
- Refresh expectations
- Index dependencies
