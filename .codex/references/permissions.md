# Sobhan Permission and Scope Reference

## Principles

- Menus do not provide security.
- Every action requires server-side permission checks.
- Every record query must apply ownership and organizational scope.
- Raw answers, financial data, payroll data, and commission details require stricter permissions.
- Export and import are separate permissions from view.

## Typical Scope

| Role | Default scope |
|---|---|
| Employee | Own records |
| Visitor | Own sales/routes/customers where assigned |
| Supervisor | Assigned line and visitors |
| Sales Manager | Assigned supervisors and lines |
| Unit Manager | Assigned unit |
| HR | Authorized employee/assessment scope |
| Finance | Authorized financial scope |
| Admin | Module-based administrative scope |
| Super Admin | Full explicit scope |

## Action Matrix Template

| Module | View | Create | Edit | Delete | Import | Export | Approve | Recalculate |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Sales Data |  |  |  |  |  |  |  |  |
| Targets |  |  |  |  |  |  |  |  |
| Commission |  |  |  |  |  |  |  |  |
| HR/KPI |  |  |  |  |  |  |  |  |
| Tests |  |  |  |  |  |  |  |  |
| Tickets |  |  |  |  |  |  |  |  |
| Settings |  |  |  |  |  |  |  |  |
