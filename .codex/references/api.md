# Sobhan API Reference

## Standard JSON Envelope

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "error": null
}
```

Error:

```json
{
  "success": false,
  "data": null,
  "meta": {},
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "اطلاعات واردشده معتبر نیست."
  }
}
```

## API Rules

- Authenticate every private endpoint.
- Use stable error codes.
- Validate method and content type.
- Apply authorization and organizational scope.
- Do not return raw exceptions.
- Paginate large lists.
- Limit export size or make it controlled.
- Use timeouts for external calls.
- Log correlation ID and safe metadata.
- Keep API versions/contracts backward compatible.

## SobhanAI

Configuration keys should be externalized, for example:

- Base URL
- API key
- timeout
- enabled flag
- model/provider selection
- allowed capabilities

Never hardcode credentials or public ports in source code.
