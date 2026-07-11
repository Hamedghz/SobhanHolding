# Sobhan Validation Checklist

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
