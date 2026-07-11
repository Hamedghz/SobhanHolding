# Sobhan Website Project Context

## Product

Internal operational and management platform for Sobhan Distribution Company.

## Runtime and Hosting

- Backend: classic PHP
- Runtime target: PHP 8.1+
- Database: MySQL / MariaDB
- Interface: Persian RTL
- Hosting: DirectAdmin / cPanel compatible
- Local development: Windows is common
- Production assumptions must be verified from repository and configuration
- No framework rewrite
- No mandatory Node or Composer build unless already present in the repository

## Main Functional Areas

1. Personal workspace and work planner
2. Executive and management dashboards
3. Sales operations and market execution
4. Sales data, targets, commissions, and calculations
5. Management reports and decision support
6. Governance, meetings, rules, and resolutions
7. Human resources and organizational structure
8. KPI, performance evaluation, assessments, and tests
9. Finance, payroll, collections, and receivables
10. Cartable, ticketing, and correspondence
11. Organizational messaging, email, and notifications
12. Knowledge base, documentation, and content
13. AI, ERP, API, and data integration
14. Settings and access control
15. Maintenance, backups, diagnostics, and system health

## Important Existing Route Families

Verify exact paths before changing them. Common route families include:

- `/admin/`
- `/employee/`
- `/install/`
- `/api/`
- `/core/`
- `/services/`
- `/assets/`
- `/uploads/`

Frequently referenced pages include:

- `/admin/index.php`
- `/admin/ceo-dashboard.php`
- `/admin/users.php`
- `/admin/employee-assessments.php`
- `/admin/hr-kpi.php`
- `/admin/hr-kpi-results.php`
- `/admin/hr-kpi-templates.php`
- `/admin/hr-settings.php`
- `/admin/sobhan-api-settings.php`

Do not assume all paths still exist. Inspect the repository.

## Organization Model

Sales depth:

`Sales Manager -> Supervisor -> Visitor`

Typical lines:

- A
- B
- C
- D

Typical sales geography:

- Zahedan, with multiple defined regions
- Zabol
- Khash
- Saravan
- Iranshahr
- Nikshahr
- Konarak
- Chabahar

A visitor may cover a defined Zahedan region plus one or more counties.

Other units may include:

- Warehouse and distribution
- Finance and treasury
- IT
- Planning
- Administration
- HR
- Procurement / commercial
- Management

## Sales Data Architecture Intent

The permanent source of truth should be database tables and reporting views.

Expected flow:

`Excel / CSV / SobhanAI API -> staging -> normalization -> validation -> mapping -> controlled upsert -> final tables -> reporting views -> dashboards`

Important source domains:

- Aggregated sales
- Inventory
- Visitors and sales hierarchy
- Customer-class coefficients
- Product priorities
- Sales targets
- Returns
- Coverage
- Brand achievement
- Commission settings

Excel and pivot tables are import/reference artifacts, not runtime business logic.

## AI and ERP Intent

Preferred architecture:

`Website API <-> SobhanAI Windows Server <-> Reporting Database / ERP`

Requirements:

- API-key authentication
- Read-only reporting access where possible
- Controlled reporting views
- Structured JSON responses
- Safe timeouts and retries
- No raw exception output
- No unrestricted AI-generated SQL
- No secrets committed to Git

## Date and Locale

- UI language is Persian.
- RTL layout is mandatory.
- Use Jalali date presentation where consistent with existing helpers.
- Keep database dates canonical and sortable.
- Normalize Persian/Arabic digits before numeric or date processing.

## Current Engineering Priorities

- Consolidate duplicated dashboards and employee panels.
- Improve the personal planner and quick task entry.
- Centralize sales data imports and reporting.
- Move commission formulas from Excel into controlled services/settings.
- Strengthen sales hierarchy and data scoping.
- Complete KPI, assessment, test, and HR analytics modules.
- Stabilize messaging, notification, and ticket workflows.
- Harden SobhanAI and ERP integration.
- Clean menu structure without breaking routes or permissions.
- Add safe diagnostics, backup, and seed/update tooling.
