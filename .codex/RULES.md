# Sobhan Project Rules

## Architecture

- Preserve classic PHP architecture.
- Do not migrate to Laravel, Symfony, React SPA, or another stack unless explicitly ordered.
- Reuse existing project helpers before creating parallel abstractions.
- Keep business logic out of templates when a service/helper layer already exists.
- Do not create duplicate pages for behavior that should be merged into an existing dashboard.
- Avoid broad refactors inside feature requests unless the refactor is required for correctness.

## Compatibility

- Maintain PHP 8.1 compatibility.
- Maintain MySQL/MariaDB compatibility.
- Maintain DirectAdmin/cPanel deployment compatibility.
- Avoid shell commands or extensions unavailable on shared hosting unless optional and documented.
- Do not require Node, Composer, Redis, WebSocket, or queue infrastructure unless the task explicitly introduces and documents it.

## Data Safety

- No destructive database statements without explicit approval.
- No production reset scripts.
- No silent data migration.
- No hardcoded business identifiers when configuration or relationships should drive behavior.
- No duplicate seed records.
- No import commit before validation summary.
- No bulk update without a stable matching key and auditability.

## Security

- Server-side authorization is mandatory.
- CSRF is mandatory for state-changing browser requests.
- Use prepared statements.
- Escape output.
- Validate uploaded files by size, extension, MIME, and content where feasible.
- Store uploads safely and prevent execution.
- Never expose raw database or PHP errors.
- Never store or log plain-text passwords, API keys, tokens, session IDs, or cookies.
- Never commit `.env`, backups, database dumps, production configs, or user uploads.

## Persian RTL UI

- Use Persian labels and user-friendly messages.
- Preserve RTL direction across forms, tables, modals, and charts.
- Use existing theme tokens and components.
- Avoid visual clutter.
- Use useful empty states.
- Verify contrast.
- Use Jalali presentation consistently with current project helpers.

## Sales Calculations

- Centralize formulas.
- Preserve precision and rounding rules.
- Keep return and cancellation signs explicit.
- Separate gross, discount, net, tax, cost, target, achievement, and commission.
- Do not infer missing business rules.
- Record formula version or period when historical reproducibility matters.

## Error Handling

- Catch expected database and integration failures.
- Log technical context internally without secrets.
- Show concise Persian messages to users.
- Keep partial writes inside transactions where supported.
- Never convert an error into a false success response.

## Git and Delivery

- Inspect `git status` before changes.
- Do not overwrite unrelated user modifications.
- Keep changes scoped.
- Review the final diff.
- Do not commit unless requested.
- Do not push unless requested.
