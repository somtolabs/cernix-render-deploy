# Official Student Registry and Photo Approval

## Admin CSV Import

Admins and super admins upload the official student list from **Admin Control > Student Registry**.

The CSV must include:

- `matric_number`
- `full_name`
- `department`
- `faculty`
- `level`

Optional columns:

- `programme`
- `academic_session`
- `status`

`status` should be `active` or `inactive`. Blank status values default to `active`.

During import, CERNIX trims spaces from each value. If a matric number already exists, the official student record is updated instead of duplicated. Invalid rows are skipped and recorded in the import summary.

## Why CSV

CSV is used because it is simple to export from common school registry tools, spreadsheet applications, and departmental records. It also keeps deployment live-ready without requiring a direct integration with a student information system on day one.

## Registration Source of Truth

Student registration now checks `official_students`. A student can continue only when the matric number exists and the official record is active.

Students cannot freely change official name, department, faculty, or level. Those values are copied from the official registry into the session student profile.

## Photo Upload Is Not Self-Verification

Student photo upload only submits a passport photo for review. It does not verify the student automatically and does not approve the profile.

After upload, the student profile status becomes `pending_admin_approval`.

## Admin Approval

Admins and super admins review photos from **Admin Control > Photo Approvals**.

Available decisions:

- Approve: profile becomes `approved`.
- Reject: profile becomes `rejected` and stores a short reason.
- Flag: profile becomes `flagged` for manual review.

Approval, rejection, and flag decisions are written to the audit log.

## QR Pass Blocking

QR pass generation is blocked until all required conditions are met:

- Matric number exists in `official_students`.
- Official student status is `active`.
- Profile/photo status is `approved`.
- Payment is verified.
- Course/exam is available.
- A QR pass has not already been generated for that course/exam.

If the profile is still pending, the student sees:

> your profile is awaiting admin approval before you can generate an exam pass.

If rejected, the rejection reason is shown when available.
