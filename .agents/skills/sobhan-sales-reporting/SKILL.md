---
name: sobhan-sales-reporting
description: Build or verify Sobhan sales dashboards, KPI metrics, targets, coverage, returns, customer analysis, and commission calculations using consistent business definitions and role scopes.
---

# Sobhan Sales Reporting

## Mandatory Checks

- Reporting grain
- Date range and Jalali/Gregorian boundary
- Invoice type and cancellation
- Return signs
- Gross sales
- Discounts
- Tax/duties
- Net sales
- Product/customer/visitor identity
- Sales hierarchy scope
- Target period
- Achievement cap/floor
- Rounding

## Workflow

1. Write metric definitions before SQL.
2. Reuse reporting views/services.
3. Apply manager/supervisor/visitor scope.
4. Reconcile against a trusted sample.
5. Do not duplicate commission formulas.
6. Document assumptions.
