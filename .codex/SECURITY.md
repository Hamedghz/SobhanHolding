# Sobhan Security Baseline

## Security Is a Guardrail, Not a Default Refusal

- High-risk classification requires broader inspection, targeted tests, security notes, migration/rollback notes, and human review.
- High risk alone does not convert an implementation request into an audit or documentation-only task.
- Prefer safe repository-side implementation with prepared statements, authorization, CSRF, validation, escaping, idempotency, and sanitized logs.
- Refuse or pause only the specific unsafe or unauthorized operation. Continue any safe in-scope implementation that does not depend on it.
- Never claim that production, live database, or authenticated browser validation passed when it was not executed.

## Authentication and Authorization

For every protected action verify:

1. User is authenticated.
2. User has the required permission.
3. User is allowed to access the target record.
4. Role and organizational scope are enforced server-side.
5. Sensitive changes are auditable.

Scope examples:

- Employee: own records only
- Supervisor: assigned team/line only
- Sales manager: assigned supervisors/lines only
- HR/Admin: authorized organizational scope
- Super Admin: explicit elevated operations

## Request Security

- Require CSRF tokens for state-changing web requests.
- Validate request method.
- Validate scalar type, range, format, and allowed enum values.
- Whitelist sortable/filterable field names.
- Reject unknown actions.
- Use consistent JSON response envelopes for AJAX/API endpoints.

## SQL Security

- Use prepared statements and bound parameters.
- Do not concatenate user input into SQL.
- Do not allow arbitrary table, column, view, or order-by values.
- Use least-privilege database users.
- AI services may query only approved reporting views or endpoints.

## File Security

- Enforce upload size.
- Validate extension and MIME.
- Generate server-side filenames.
- Keep executable files out of upload directories.
- Prevent path traversal.
- Avoid storing sensitive exports under public URLs.
- Protect backup and export downloads with authorization.

## Secrets

Never commit or print:

- Database credentials
- API keys
- SMS credentials
- OpenAI or model-provider keys
- ERP credentials
- Mail passwords
- Session secrets
- Private server IP mappings when output is public
- Private keys or certificates

Use configuration or environment variables. Provide `.example` files with placeholders only.

## Integration Security

- Use API keys or signed requests.
- Apply timeouts.
- Limit retries.
- Validate response schema.
- Do not trust remote HTML or exception text.
- Redact secrets from logs.
- Use HTTPS where available.
- Limit public exposure and firewall rules.

## Error Policy

User-facing response:

- Persian
- Non-technical
- Actionable
- No stack trace
- No SQL
- No credentials
- No internal file path

Internal log:

- Timestamp
- Request/action identifier
- Safe user identifier
- Module
- Error category
- Sanitized context
- Correlation ID where supported
