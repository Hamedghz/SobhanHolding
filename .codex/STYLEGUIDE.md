# Sobhan Code and UI Style Guide

## PHP

- Follow the existing file style first.
- Prefer strict, explicit validation.
- Keep functions focused.
- Use early returns for failed guards.
- Avoid deeply nested conditionals.
- Use descriptive names.
- Add comments for business reasons, not obvious syntax.
- Avoid global state when an existing service/helper pattern is available.
- Do not suppress warnings to hide defects.

## SQL

- Name columns and aliases clearly.
- List selected columns explicitly in large/reporting queries.
- Paginate large result sets.
- Use stable ordering.
- Keep aggregation grain explicit.
- Document date boundaries and return/cancellation handling.
- Prefer reusable views/services for repeated metrics.

## HTML and UI

- Escape dynamic output.
- Use semantic labels.
- Keep button labels Persian.
- Match existing classes and layout components.
- Provide loading, empty, success, and error states.
- Keep destructive actions visually and behaviorally distinct.
- Confirm destructive actions.
- Preserve mobile usability.

## JavaScript

- Avoid introducing a framework for isolated behavior.
- Reuse project AJAX and modal patterns.
- Keep server-side validation authoritative.
- Handle network errors and duplicate submission.
- Do not embed secrets or privileged logic in the browser.

## Naming

Follow repository conventions. When no clear convention exists:

- PHP classes: `PascalCase`
- Functions/methods: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- Database tables/columns: `snake_case`
- Permission keys: stable dotted or snake-case convention already used by project
- Route filenames: match existing admin/employee naming style

## Documentation

For new modules document:

- Purpose
- Entry routes
- Permissions
- Tables and migrations
- Configuration
- Validation commands
- Deployment notes
- Known limitations
