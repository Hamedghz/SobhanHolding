# Sobhan Project Rules

## Task Mode and Phase Execution

Determine the task mode from the user's latest explicit request.

### Implementation Mode

Use implementation mode when the user asks to implement, fix, update, build, refactor, harden, complete, continue, or execute a named phase.

- Execute the requested repository changes in the same task.
- A phase prompt is not documentation-only by default.
- Work only on the requested phase; do not silently advance to later phases.
- A newer explicit phase request authorizes that phase even if an earlier phase said not to implement it yet.
- Start with a short impact analysis, then inspect the relevant code, apply the smallest safe patch, run targeted validation, and review the diff.
- High risk changes the validation depth; it is not by itself a reason to refuse implementation.
- When part of a task is blocked, complete every safe in-scope part that remains possible and report the exact blocker.

Implementation is complete only when:

- The requested behavior is implemented in code, configuration, schema/seed, tests, or executable scripts as applicable.
- Targeted checks were actually run, or a concrete environment blocker is reported.
- Documentation is updated when behavior, setup, migration, API, or workflow changed.
- The final response distinguishes completed work from unverified live or production checks.

### Read-Only Mode

Use read-only mode only when the user explicitly asks for audit, review, analysis, diagnosis, report, planning, documentation-only work, or says not to change code.

- Do not modify repository files, data, secrets, or external systems.
- Provide evidence, findings, risks, and recommended changes.
- Do not turn an implementation request into read-only mode merely because the area is sensitive.

### Markdown Is Supporting Work

- Markdown is supporting work, not a substitute for implementation.
- Do not satisfy an implementation phase by creating or updating only `*.md` files unless the user explicitly requested a documentation-only phase.
- Create or update documentation after or alongside the functional change, not instead of it.
- If repository evidence shows that no functional change is needed, explain that evidence and run the relevant verification instead of manufacturing a documentation-only deliverable.

### Legitimate Blockers

Stop or request user action only when the required step needs unavailable credentials, production access, destructive approval, a missing business decision that materially changes the result, or an external dependency that cannot be safely bypassed. General risk, a dirty working tree, missing optional tooling, or incomplete live access does not block safe repository-side implementation when it can still proceed without overwriting unrelated work.

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
