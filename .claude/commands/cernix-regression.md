Run the CERNIX regression prevention protocol for the change the user describes.

Steps:
1. Read the current implementation of every file to be changed.
2. Grep for all callers of changed methods, route names, and Blade variables.
3. List every test in tests/Feature/ that touches the changed area.
4. Make the change.
5. Run: php artisan test
6. Run: php artisan view:cache
7. Report: tests before vs after, any new failures, any new Blade errors.
8. If any test fails: diagnose root cause, fix, re-run. Never skip.

Key invariants that must never break:
- Payment: tests and makeups never require RRR regardless of settings
- QR: HMAC checked before decrypt; DUPLICATE returned on repeat scan; REJECTED on invalid
- Attendance: checked_in → submitted is one-way; never revert
- Photo approval: photo_status transitions are admin-only
- official_students: must exist before registration proceeds
- Student session: student_matric_no + student_session_id must both be present on protected routes
- Examiner session: examiner_id + examiner_role must both be present on protected routes
- Branding: Branding::logoUrl(), systemName(), institutionName() always resolve (never null-crash)

Do not mark a task complete until php artisan test reports 0 failures.
