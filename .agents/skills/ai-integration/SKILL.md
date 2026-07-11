---
name: ai-integration
description: Implement or review SobhanAI, OpenAI, local-model, ERP, SMS, email, or external API integration with controlled contracts, secrets management, timeouts, safe retries, and sanitized logs.
---

# AI and API Integration

## Rules

- Configuration only; no hardcoded secret.
- Authenticate both inbound and outbound requests.
- Use timeouts.
- Validate response schema.
- Use bounded retry.
- Avoid duplicate side effects.
- Sanitize logs.
- Provide health/status endpoints.
- Fail safely when disabled or unavailable.
- AI may access approved views/services only.
- Never execute unrestricted model-generated SQL.

Document endpoint, method, auth, request, response, errors, timeout, retry, and ownership.
