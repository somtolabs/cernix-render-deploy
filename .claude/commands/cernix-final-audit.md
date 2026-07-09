Run a complete CERNIX final audit before ending any implementation task.

Execute each check and report PASS / FAIL / SKIP (with reason):

1. Routes — php artisan route:list | grep for all routes referenced in changed views
2. Controllers — read each changed controller method; no undefined variables, no missing methods
3. Blade — php artisan view:cache; must complete with zero errors
4. Migrations — all new DB columns referenced in code exist in a migration file
5. Storage — upload disk paths configured; directories writable in production
6. UI 390px — mobile layout collapses correctly (mentally trace the CSS)
7. UI 1280px — desktop layout uses available space well, no overflow
8. Permissions — role checks present on every changed admin/superadmin action
9. Auth guards — examiner_id + examiner_role on examiner routes; student_matric_no on student routes
10. Tests — php artisan test; must report 0 failures
11. Settings — no visible dead controls in settings UI
12. Media — ID card, selfie, profile photo all separately renderable by admin
13. Payment — tests and makeups never show RRR field; exams respect settings
14. Attendance — checked_in → submitted flow accessible from examiner portal
15. Risk intelligence — no-submissions list updates when attendance records change

Only report DONE when all checks PASS or have a justified SKIP.
Then output the Implementation Summary block.
