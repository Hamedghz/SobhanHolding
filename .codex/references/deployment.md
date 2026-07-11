# Sobhan Deployment Reference

## Before Deployment

- Review diff.
- Back up affected source files.
- Back up database when schema/data changes.
- Record current version/commit.
- Verify PHP and database compatibility.
- Identify writable directories.
- Verify config variables.
- Prepare rollback steps.

## Deployment Order

1. Maintenance or low-traffic window when needed.
2. Source backup.
3. Database backup.
4. Upload source changes.
5. Apply safe migration/seed.
6. Clear only documented caches.
7. Validate health and login.
8. Validate affected role flows.
9. Review logs.
10. Close maintenance window.

## Rollback

Document:

- Files to restore
- Database restore or reverse migration
- Config rollback
- Service restart
- Verification after rollback

Never deploy an untested destructive migration.
