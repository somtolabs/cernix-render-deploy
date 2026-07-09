Run a CERNIX security audit on the area the user specifies.

Check each:
- Admin/examiner routes: session('examiner_id') guard present
- Super admin actions: $currentAdmin['is_super_admin'] check present
- Student routes: session('student_matric_no') guard present
- File uploads: MIME validation, extension whitelist, stored outside webroot or signed URL
- QR tokens: HMAC verified before decrypt; expired tokens caught; single-use enforced if enabled
- official_students: checked before registration and QR generation
- Photo paths: sanitised before DB insert; no user-controlled paths
- Settings writes: super admin only; validated before DB write
- Role checks: examiner_role is 'admin'|'superadmin'|'examiner' — never assume
- SQL: raw DB::table() used safely; no user input interpolated into query strings
- CSRF: all non-GET forms have @csrf
- QR HMAC: checked before AES decrypt, not after
- Token expiry: checked against current time, not just presence
- Duplicate scan: DUPLICATE status returned, not APPROVED, on repeat scans
- Exit scan authorization: only examiners can mark submission, not students
- Import validation: CSV import validates all rows before inserting any
- Photo flag reason: sanitised before storing in DB

Report each item as SECURE / VULNERABLE / REVIEW-NEEDED with file:line.
