Run the CERNIX production-readiness audit on the area the user specifies.

Think like the system is going live right now. Check each:

Feature flags:
- SystemMode::isLive() gates demo-only features correctly
- No demo RRR values (TEST-*) accepted in live mode
- MockSIS is NOT reachable from live code paths

Data integrity:
- All forms have CSRF protection (@csrf)
- Redirects after POST use redirect()->route(), not redirect()->back() for critical flows
- No hardcoded matric numbers, session IDs, or test credentials in views
- Settings that are wired but non-functional are removed from UI or hidden

File uploads:
- MIME type validated (not just extension)
- PHP/executable extensions rejected
- Files stored outside public root (local disk) or served via signed URL
- Upload size limits enforced

Persistence:
- New columns exist in migrations before referenced in code
- No raw DB column references without Schema::hasColumn() guard
- Seeders safe to run in production (guard with SystemMode::isDemo())
- Exception: MockSISSeeder always runs (HealthController counts mock_sis records)

Permissions:
- Admin routes check session('examiner_id')
- Super-admin actions check $currentAdmin['is_super_admin']
- Student routes check session('student_matric_no')
- No route accessible without the correct guard

Student lifecycle:
- official_students lookup before registration AND QR generation
- payment_required=false on test/makeup types — never requires RRR
- Photo approval gate enforced before QR generation when setting is on
- Attendance records created on APPROVED scan only, never on DUPLICATE or REJECTED

Attendance states:
- checked_in → submitted is one-way; no revert allowed
- Students checked in but not submitted are flagged in risk intelligence
- Submission can only be recorded by examiners, not students

Report PASS / FAIL / REVIEW-NEEDED for each item.
