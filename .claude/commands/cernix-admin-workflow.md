Audit the CERNIX admin workflow for the area the user specifies.

Admin responsibilities in priority order:
1. Dashboard — risk alerts, pending approvals, active session metrics, today's exams
2. Student registry — import CSV, view records, trace students, resolve import failures
3. Photo approvals — ID card + selfie review separately, approve/reject/flag with reason
4. Assessment management — timetable CRUD: exams, tests, makeups, per-row payment flag
5. Attendance — check-in status, submission status, anomalies, not-submitted flag
6. QR token management — revoke, audit, re-issue
7. Examiner management — create, toggle active, assign roles
8. Reports and exports — attendance, payment, registry
9. Notes / internal communication — per-student, per-examiner
10. Session settings — activate/close exam session (admin scope)

Checklist (PASS / FAIL for each):
- Dashboard shows accurate live metrics; no stale placeholders
- Student registry table is filterable, paginated, scannable
- Photo approval queue shows ID card AND selfie as separate images
- Assessment pages distinguish exam / test / makeup clearly
- Attendance shows checked_in vs submitted vs not-submitted vs flagged counts
- Students checked-in but not submitted are visually flagged
- QR token audit log is accessible and revocation works
- Examiner list shows active/inactive status; toggle works
- Notes visible to correct audiences only
- All table rows have accessible action links or buttons
- Filters and search do not break layout at 390px
- Import errors shown with row-level detail
- All admin actions write to audit log

Optimize for admin speed. Tables must be fast, filterable, and scannable.
Flag any action that is visible in the UI but does not function.
Flag any info duplicated on the same screen.
