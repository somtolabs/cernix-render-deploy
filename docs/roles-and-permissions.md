# CERNIX Roles And Permissions

This document summarizes the implemented role model used by the current CERNIX app.

Role values are stored in lowercase form:

- `examiner`
- `admin`
- `super_admin`

The application normalizes role values before checking access.

## Examiner

Examiners use only the Examiner portal.

Allowed:

- Log in at `/examiner/login`.
- Use the live QR scanner.
- View own scanner workflow pages such as scan history, today’s exams, student records, audit trail, and examiner notifications where enabled.

Not allowed:

- Access `/admin/*` routes.
- Manage students, payments, settings, roles, or system-wide configuration.
- Log in through the Admin portal.

## Admin

Admins use the Admin portal for operational monitoring.

Allowed:

- Log in at `/admin/login`.
- View dashboard, students, payments, timetable, scan logs, audit/activity views, notes, notifications, and risk intelligence.
- Manage regular operational records where the current UI exposes safe controls.

Restricted:

- Cannot enter the Examiner portal.
- Cannot create or deactivate Super Admin users.
- Cannot change role hierarchy or sensitive system-wide controls reserved for Super Admin.
- Cannot use Super Admin-only maintenance or settings actions.

## Super Admin

Super Admin uses the Admin portal, not the Examiner portal.

Allowed:

- Access all Admin-level pages.
- Manage role-sensitive settings where implemented.
- Manage fee mapping, active session controls, examiner/admin management, notes, audit views, and risk intelligence where the current UI exposes those actions.
- Create or manage higher-privilege accounts where the current user-management screen supports it.

Restricted:

- Cannot log into the Examiner portal with Super Admin credentials.
- Should use a separate Examiner account if the scanner workflow needs to be tested.

## Notes

- Admin and Super Admin routes are protected server-side.
- Examiner routes allow only the `examiner` role.
- Admin routes allow `admin` and `super_admin`.
- Student-facing routes are separate from examiner/admin authentication.

## Future Enhancements

The following roles are future options and should not be documented as active until implemented:

- Exam Officer
- Department Admin
- Auditor
