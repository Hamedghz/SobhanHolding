# Sales Structure and Territory Model

This change adds a central model for Sobhan's sales hierarchy and field coverage:

```text
Sales Manager
↓
Sales Supervisor
↓
Sales Line
↓
Visitors
↓
Cities / Zahedan Regions
```

## Business rules covered

- A sales manager can manage multiple supervisors.
- Each supervisor is responsible for one active sales line.
- Each sales line can have multiple visitors.
- Each sales line can have its own brand list.
- Cities are seeded as: زابل، زاهدان، خاش، سراوان، ایرانشهر، نیکشهر، کنارک، چابهار.
- Zahedan is split into three regions: زاهدان منطقه ۱، زاهدان منطقه ۲، زاهدان منطقه ۳.
- Every visitor can be assigned to one Zahedan region and one or more additional cities/regions.

## New tables

- `sales_lines`
- `sales_line_brands`
- `sales_geographies`
- `sales_visitor_territories`
- `sales_structure_audit_logs`

## Admin page

```text
/admin/sales-structure.php
```

The page supports:

- Define and edit sales lines.
- Link each line to a sales manager and responsible supervisor.
- Add brands to each line.
- Manage cities and regions.
- Assign visitor territories.
- View summary tables for lines, brands, geographies, and visitor coverage.
- Diagnose lines without manager/supervisor, duplicated active supervisor assignments, visitors without territory, visitors without an active Zahedan region, and multiple primary territories per visitor/line.

## Validation rules

- Line code and title are required and line codes are unique.
- Selected manager, supervisor, and visitor must be active users with the matching sales role.
- One supervisor cannot be assigned to more than one active line.
- A city cannot have a parent; a region must have an existing city parent.
- A visitor assignment requires at least one active geography and stores exactly one primary geography for the selected line.
- Incomplete legacy data is reported as an admin warning instead of blocking unrelated saves.

## Installation

Run the standard admin maintenance seed page and execute:

```text
sales_structure
```

The page also calls `SalesStructureModule::repair()` defensively, so missing tables are repaired safely when an authorized admin opens the module.

## Compatibility

The module does not replace existing user/org fields. It complements the existing fields already used by `users.php` and `hr-settings.php`:

- `users.kara_system_code`
- `users.sales_line_id`
- `users.sales_line`
- `users.supervisor_id`
- `users.organization_manager_id`
- `users.parent_user_id`
- `manager_employees`

When a line or territory is saved, these compatibility fields are updated safely so existing manager/supervisor/visitor reports can continue to work.

- Saving a line syncs the supervisor's `sales_line_id`, compatibility `sales_line`, manager parent fields, and the manager-to-supervisor `manager_employees` link.
- Saving visitor territories syncs `sales_line_id`, compatibility `sales_line`, `supervisor_id`, `parent_user_id`, `organization_manager_id`, and the supervisor-to-visitor link.
- The normal visitor import/mapping workflow is retired; visitor identity and hierarchy are managed through the central user and sales-structure pages.

## Testing checklist

1. Run the `sales_structure` seed twice and confirm counts do not grow unexpectedly.
2. Confirm lines A/B/C/D, all eight cities, and the three Zahedan regions exist.
3. Assign an active sales manager and sales supervisor to a line; verify compatibility user fields and `manager_employees`.
4. Add line brands and assign a visitor to one Zahedan region plus another city.
5. Confirm exactly one primary territory remains for that visitor and line.
6. Confirm warning cards identify incomplete legacy assignments.
7. Confirm a view-only user cannot POST, an unauthorized user cannot open the page, and no raw database error is rendered.

## Future integrations

The helper queries are intended as the shared source for sales reports, commission and target calculations, customer coverage, 3-3-3 analysis, supervisor/manager scoping, and AI Insight. Those calculations are not changed by this PR.
