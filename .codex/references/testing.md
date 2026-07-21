# Sobhan Validation Checklist

## Codex Policy Contract

- `.codex/scripts/policy-contract-test.ps1` passes.
- Implementation and phase prompts require repository edits and targeted validation when changes are needed.
- Explicit audit/report/plan-only prompts remain read-only.
- High-risk wording increases validation requirements without forcing documentation-only output.
- Markdown cannot be the sole deliverable for an implementation phase unless the user explicitly requested documentation-only work.

## PHP

- Changed PHP files pass `php -l`.
- Required includes resolve.
- No duplicate function/class definitions.
- No raw warnings/notices in expected paths.

## Security

- Authentication checked.
- Permission checked.
- Record scope checked.
- CSRF checked for writes.
- Input validated.
- Output escaped.
- SQL parameterized.
- Upload security checked where relevant.

## Database

- Migration is idempotent.
- Existing data preserved.
- Unique keys prevent duplicates.
- Transaction behavior reviewed.
- Index impact reviewed.
- Seed rerun tested where possible.

## UI

- Persian labels.
- RTL layout.
- Jalali date behavior.
- Desktop.
- Mobile.
- Empty state.
- Error state.
- Permission-denied state.
- Duplicate submission prevention.

## Sales/Financial

- Date range verified.
- Return/cancellation rules verified.
- Gross/net/discount/tax definitions verified.
- Rounding verified.
- Sample output reconciled.

## Integrations

- Health endpoint.
- Authentication failure.
- Timeout.
- Invalid response.
- Retry behavior.
- Log sanitization.
- Disabled-state behavior.
