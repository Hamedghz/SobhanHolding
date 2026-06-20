# Windows Server API requirements

The PHP hosting panel must call these authenticated Windows Server endpoints. It must not run local shell commands or long-running indexers.

## Required endpoints

1. `POST /api/knowledge/index`
2. `GET /api/knowledge/index/status/{job_id}`
3. `POST /api/dashboard/refresh`
4. `GET /api/dashboard/status/{job_id}`
5. `POST /api/ai/complete-settings`
6. `POST /api/sql/reporting-refresh`

All endpoints must require `X-API-Key`, return JSON, accept no command or executable path, and use fixed server-side operations only.

### Knowledge request

```json
{
  "source_type": "site|files|database|all",
  "source_id": null,
  "requested_by": "admin-user-id",
  "callback_url": null,
  "options": {
    "rebuild": false,
    "chunk_size": 800,
    "language": "fa"
  }
}
```

### Job response

```json
{
  "ok": true,
  "job_id": "remote-job-id",
  "status": "queued|running|completed|failed",
  "progress": 0,
  "message": "Safe user-facing summary"
}
```

Do not return API keys, commands, Windows paths, stack traces, or raw database errors.
