# Sobhan Architecture Reference

## Preferred Request Flow

`Route/Page -> Authentication -> Authorization -> CSRF/Validation -> Service -> Repository/DB -> Response/View -> Audit/Log`

Avoid:

- SQL directly scattered through views
- Permission checks only in menus
- Business formulas duplicated in pages
- Raw external API calls from templates
- Direct database coupling between website and internal ERP/AI systems

## Data Flow for Imports

`Upload/API Pull -> Import Batch -> Staging -> Header Normalization -> Column Mapping -> Row Validation -> Error Report -> Controlled Upsert -> Reporting Refresh`

## Reporting Flow

`Final Operational Tables -> Reporting Views -> Shared Report Service -> Role-Scoped Dashboard`

## AI Flow

`User Question -> Intent/Permission Check -> Approved Metric/Query Plan -> Controlled API/View -> Structured Data -> AI Explanation`

AI must not receive unrestricted database credentials.

## Module Boundaries

- Core/auth/permissions
- Organizational structure
- Personal workspace/planner
- Sales operations
- Sales data and imports
- Targets and commissions
- Reporting and dashboards
- HR/KPI/assessments/tests
- Finance and receivables
- Cartable/ticketing/messaging
- AI and integrations
- Settings and maintenance

A feature may cross modules, but ownership and dependencies must remain explicit.
