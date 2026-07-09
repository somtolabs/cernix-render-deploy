Trace the complete CERNIX student workflow end-to-end.

Stages to verify (report COMPLETE / PARTIAL / MISSING for each):

1. official_students import — CSV parsed, validated, inserted, import failures visible in admin
2. /student/register — matric lookup → onboard redirect
3. /student/onboard — identity confirmed, password set, ID card + selfie uploaded
4. Photo approval queue — admin sees ID card + selfie separately, can approve/reject/flag
5. /student/dashboard — eligibility computed from photo_status + payment + official registry
6. Assessment eligibility rules:
   - exam: payment required only if default_exam_payment_required = true (or per-row override)
   - test: NEVER requires payment regardless of any setting
   - makeup: NEVER requires payment regardless of any setting
7. /student/generate-exam-pass — RRR field hidden when payment not required, QR token created, encrypted, HMAC signed
8. Examiner entry scan — APPROVED / DUPLICATE / REJECTED decision recorded in qr_verification_logs
9. Attendance check-in — attendance_records row created with status=checked_in
10. Submission confirmation — exit scan or manual submission marks status=submitted
11. Attendance completion — status=completed after examiner closes the session or final action
12. Scan history — /student/dashboard shows all past scans with status labels
13. Student media — profile_photo_path (dashboard) and photo_path + id_card_path (verification) remain separate

For each stage: flag payment rule violations, missing guards, broken UI elements.
Never break this flow.
