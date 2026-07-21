# Sobhan Engineering Task Board

## Task Board Execution Contract

- Selecting a backlog item for implementation means perform the code/config/schema/test work needed for that item, not only write a plan or `*.md` file.
- A prompt such as `Phase N`, `فاز N`, `continue phase`, or `execute phase` is implementation mode unless it explicitly says documentation-only, audit-only, or no code changes.
- Complete only the selected phase and its required validations. Do not automatically start the next phase.
- An audit/review item stays read-only only when the current prompt explicitly requests audit, review, diagnosis, report, or planning.
- Documentation remains part of the definition of done when applicable, but it is not the sole deliverable for an implementation task.

## Current Focus

- [ ] Audit and consolidate dashboard and employee-panel duplication
- [ ] Improve personal planner quick-add, date handling, carry-forward, and daily views
- [ ] Centralize sales data import and reporting sources
- [ ] Build controlled inventory, target, visitor, coefficient, and priority imports
- [ ] Move commission formulas into versioned services and settings
- [ ] Complete sales hierarchy, regions, routes, and data scopes
- [ ] Complete HR/KPI/assessment/test flows and reporting
- [ ] Stabilize ticket, messaging, and notification event delivery
- [ ] Harden SobhanAI API integration and health diagnostics
- [ ] Clean and document menu structure and permissions
- [ ] Add idempotent schema/seed update tooling
- [ ] Add backup, diagnostics, and deployment runbooks

## Quality Backlog

- [ ] Map all routes to permissions
- [ ] Map all tables and ownership scopes
- [ ] Identify duplicated formulas and queries
- [ ] Add changed-file PHP lint workflow
- [ ] Add targeted regression checklist per module
- [ ] Add import batch history and error reports
- [ ] Add calculation versioning
- [ ] Add audit logs for sensitive admin actions
- [ ] Add API response contract tests
- [ ] Add Persian/Jalali date boundary tests

## Do Not Modify Without Explicit Task Scope

- Authentication/session flow
- Core permission semantics
- Production database credentials
- Production deployment configuration
- Payroll and financial posting logic
- Commission formulas not supplied or verified
- User deletion/merge behavior
- Existing backup retention rules
- ERP integration contract
- Public network/NAT/firewall configuration
