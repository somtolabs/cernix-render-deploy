Audit CERNIX super-admin governance features for the area the user specifies.

Super admin controls things that affect the entire system:
- system_mode (live/demo) — banner must reflect current mode
- system_name, institution_name, branding_logo — all views update when saved
- Feature flags: require_photo_approval_before_qr, allow_payment_not_required_exams,
  default_exam_payment_required, attendance_tracking_enabled, enable_submission_scan,
  qr_single_use_enforced, auto_flag_unverified_before_exam, photo_resubmit_allowed
- Session management — activate/close exam sessions, set session dates
- Demo data purge — removes only demo data; live data unaffected
- Danger zone — clear live data; super admin only with confirmation gate
- Audit retention policy
- Examiner/admin account management (create, toggle, role assignment)

Governance checklist (PASS / FAIL / NOT-IMPLEMENTED for each):
- system_mode toggle works and demo banner reflects it
- system_name and institution_name update all views when saved
- Logo upload stores correctly, branding logo updates globally
- require_photo_approval_before_qr: off = students skip approval before QR generation
- allow_payment_not_required_exams: per-row override works for exams only
- default_exam_payment_required: off = no exam requires payment unless per-row override
- Tests and makeups NEVER require payment regardless of any setting
- enable_submission_scan: on = examiner portal shows exit scan UI
- Session activate/deactivate gates student portal access correctly
- Demo purge removes only SystemMode::isDemo()-tagged data
- Danger zone requires super admin confirmation (separate from admin)
- Every visible setting either works end-to-end or is hidden until implemented

No dead controls. No placeholder sections. No visible but non-functional toggles.
Flag every setting that is visible but non-functional with file:line.
