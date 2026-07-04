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

## Installation

Run the standard admin maintenance seed page and execute:

```text
sales_structure
```

The page also calls `SalesStructureModule::repair()` defensively, so missing tables are repaired safely when an authorized admin opens the module.

## Compatibility

The module does not replace existing user/org fields. It complements the existing fields already used by `users.php` and `hr-settings.php`:

- `users.sales_line`
- `users.supervisor_id`
- `users.organization_manager_id`
- `users.parent_user_id`
- `manager_employees`

When a line or territory is saved, these compatibility fields are updated safely so existing manager/supervisor/visitor reports can continue to work.
