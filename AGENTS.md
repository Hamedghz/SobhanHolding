# Sobhan Website — Codex Project Instructions

## Project Identity

You are working on the Sobhan Distribution Company internal web platform.

Primary characteristics:

- Classic PHP admin panel
- PHP 8.1+ compatible
- MySQL / MariaDB
- Persian RTL interface
- DirectAdmin / cPanel compatible
- No Laravel rewrite
- No SPA rewrite
- No mandatory Composer dependency
- No mandatory Node build
- Existing modules must remain operational and backward compatible

The platform covers sales operations, management dashboards, HR, KPI, assessments, tests, planner, ticketing, organizational communication, financial workflows, reporting, AI integration, and system maintenance.

## Instruction Sources

Read these files before making material changes:

- `.codex/CONTEXT.md`
- `.codex/RULES.md`
- `.codex/SECURITY.md`
- `.codex/STYLEGUIDE.md`
- Relevant files under `.codex/references/`
- Relevant skill under `.agents/skills/`

Repository code is the final source of truth. Documentation describes intent, but never assume a path, table, permission key, or helper exists without verifying it.

## Core Delivery Rules

- Preserve the current architecture and route structure.
- Prefer small, reviewable, reversible changes.
- Do not rewrite the platform to another framework.
- Do not remove or silently replace existing features.
- Do not add a production dependency unless the task clearly requires it.
- Reuse existing bootstrap, authentication, permissions, CSRF, database, layout, flash-message, logging, and utility patterns.
- Keep backward compatibility unless the requested change explicitly replaces an old behavior.
- Never expose secrets, tokens, passwords, private keys, connection strings, internal stack traces, raw SQL errors, or server paths in UI or logs.
- Do not perform destructive production actions.
- Do not claim a live test passed unless it was actually executed.

## Business Structure

The sales hierarchy is:

`Sales Manager -> Sales Supervisor -> Visitor / Sales Representative`

Additional constraints:

- Sales lines are typically A, B, C, and D.
- Each supervisor manages one sales line unless the repository data model explicitly supports otherwise.
- Visitors belong to a line, supervisor, manager, region, and route scope.
- Other organizational units usually use a shallower direct-manager structure.
- Data visibility must be scoped by role, organizational unit, manager, supervisor, sales line, and employee ownership.

Never infer authorization from UI visibility alone. Enforce it server-side.

## High-Risk Domains

Treat these as high-risk and inspect all related code before editing:

- Authentication and session lifecycle
- Permission and role management
- Production database migrations and seeds
- User deletion or identity merge
- Sales, target, commission, discount, return, tax, financial, payroll, and receivable calculations
- File uploads and backups
- ERP and SobhanAI integration
- Notifications and messaging
- Scheduled jobs and deployment scripts
- Import pipelines and bulk data updates

## Change Protocol

Before changing code:

1. Read the target file and its includes.
2. Trace related routes, forms, AJAX endpoints, services, repositories, schema, permissions, and menus.
3. Search for duplicate or legacy implementations.
4. Identify the smallest safe change.
5. Preserve current conventions.
6. Apply the change.
7. Run syntax and targeted validation.
8. Review the final diff.
9. Document risks and unverified areas.

## Database Protocol

- Never use `DROP`, `TRUNCATE`, destructive rename, or irreversible data conversion without explicit approval.
- Prefer idempotent migrations and repair scripts.
- Check table, column, key, and index existence before creation.
- Preserve existing rows.
- Keep schema changes separate from seed data where practical.
- Seed scripts must be repeatable and deduplicate by stable business keys.
- Use prepared statements or the repository's safe database abstraction.
- Use transactions for multi-step writes when supported.
- Add indexes only for demonstrated query patterns.
- Do not scatter reporting SQL across unrelated view files.
- For large imports, use staging, validation, error reporting, and controlled commit/upsert.

## UI and UX Protocol

- Persian RTL is the default.
- Use the existing Sobhan layout, sidebar, components, badges, forms, tables, and flash messages.
- Use Persian/Jalali date presentation where the project expects it.
- Store canonical dates in a database-safe format; convert only at boundaries.
- Keep forms simple and minimize clicks.
- Support desktop and mobile.
- Never display raw technical errors.
- Avoid white text on white cards and other low-contrast states.
- Do not introduce a visually isolated design system into one page.

## Sales and Reporting Protocol

- Define every metric precisely.
- Keep gross sales, discounts, returns, tax, duties, net sales, cost, target, achievement, and commission distinct.
- Use consistent date ranges and Persian digit normalization.
- Do not calculate sensitive metrics independently in multiple pages.
- Prefer shared services and reporting views.
- Excel is an input source, not the platform's permanent source of truth.
- Imported and synced data must pass through a shared validation and mapping pipeline.

## SobhanAI and ERP Protocol

- Website code must not connect directly to arbitrary internal database tables.
- Use controlled API contracts or reporting views.
- Keep external endpoints, API keys, credentials, and ports in configuration, never hardcoded.
- Fail closed on authentication errors.
- Apply timeouts, safe retries, structured logs, and sanitized error messages.
- AI must not execute unrestricted SQL generated by users.
- AI data access should be read-only and limited to approved views or services.

## Validation Expectations

For PHP changes:

- Run `php -l` on every changed PHP file.
- Check includes and route entry points.
- Validate CSRF and authorization on write actions.
- Validate server-side input handling.
- Review HTML escaping.
- Check empty states and error paths.

For database changes:

- Validate idempotency.
- Document affected tables and keys.
- Provide rollback or restoration notes.
- Do not execute against production unless explicitly requested.

For UI changes:

- Check RTL alignment.
- Check desktop and mobile behavior.
- Check role-based visibility and server-side access.
- Check Persian dates and labels.

## Required Final Response

### Summary
What changed and why.

### Files Changed
Exact paths and purpose.

### Validation
Commands and checks actually completed.

### Database Impact
Tables, columns, indexes, seeds, or none.

### Security and Permissions
Authorization, CSRF, validation, and data-scope impact.

### Risks / Unverified
Anything not tested live or requiring production verification.

### Next Action
One concrete follow-up action only when necessary.
