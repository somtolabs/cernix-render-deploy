# CERNIX — Persistent Project Skills

This file is loaded automatically in every Claude Code session for this project.
All skills below apply to every task, every response, every change.

---

## Project Identity

**CERNIX** is a university exam management system built with Laravel 11 (PHP), Blade templates, MySQL, and a session-based auth model (no Eloquent models for most entities — raw `DB::table()` queries throughout).

**Deployment:** Render (render.yaml present). Branch: `main`. Do NOT push or deploy without explicit instruction.

**Institution:** Adekunle Ajasin University (configurable via `cernix_settings.institution_name`).

---

## SKILL 1 — Architecture

Before touching any file:
- Read the file. Understand its full scope.
- Trace every dependency: controller → service → support class → blade view.
- Never recreate what already exists. Search first.
- Prefer modifying existing components over creating new ones.
- Three lines repeated is acceptable. A premature abstraction is not.
- Services live in `app/Services/`. Support/helpers live in `app/Support/`.
- Auth: admin/examiner uses session keys (`examiner_id`, `examiner_role`, `examiner_username`). Student uses `student_matric_no`, `student_session_id`.
- Settings are stored in `cernix_settings` key-value table. Read via `DB::table('cernix_settings')->where('key', $k)->value('value')`. Never assume a key exists.
- Branding: `Branding::logoUrl()`, `Branding::systemName()`, `Branding::institutionName()` — globally shared as `$brandingLogoUrl`, `$brandingSystemName`, `$brandingInstitutionName` via `AppServiceProvider`.
- `SystemMode::isDemo()` / `SystemMode::isLive()` — checks `cernix_settings.system_mode`. Falls back to `demo_mode_enabled` setting or `CERNIX_DEMO_MODE` env. Always returns true for `testing` env via `DepartmentFees::isDemoMode()`.
- CSS design tokens: `--navy`, `--bg`, `--bg-2`, `--ink`, `--ink-2`, `--ink-3`, `--ink-4`, `--line`, `--line-2`, `--emerald`, `--red`, `--amber`. Never use `--primary` or `--card`.

---

## SKILL 2 — UI/UX Audit

Every UI change must pass this checklist before shipping:
- Spacing is consistent (use `clamp()` for responsive, fixed px for small elements).
- Typography has clear hierarchy: page title > section title > label > body > caption.
- No floating icons. No orphaned decorative elements.
- No unnecessary card nesting (card inside card inside card = wrong).
- No duplicate information on the same screen.
- Tables have `min-width` guards and `overflow-x: auto` wrappers.
- Mobile layouts collapse gracefully. Test at 390px.
- Empty states are informative, not blank.
- Status badges use the existing chip/pill system — never raw colored spans.
- No emojis. Ever. Not in UI, not in copy, not in code comments.
- Preserve CERNIX design language: muted greens, navy, warm off-white background, JetBrains Mono for matric numbers.
- Improve usability first. Aesthetic polish is secondary.

---

## SKILL 3 — Production Readiness

Every feature must behave correctly in live mode:
- `SystemMode::isLive()` must gate demo-only features.
- No hardcoded matric numbers, session IDs, or test credentials in views.
- File uploads must validate MIME type, size, and store to the correct disk path.
- All forms must have CSRF protection.
- Redirects after POST must use `redirect()->route()`, not `redirect()->back()` for critical flows.
- Settings that are wired but non-functional must be either completed or removed from the UI. No dead controls.
- Seeders that insert demo data must be safe to run in production (guard with `SystemMode::isDemo()` or `app()->environment('testing')`).
- Exception: `MockSISSeeder` must always run (HealthController counts mock_sis records).

---

## SKILL 4 — Security Audit

Check these on every change that touches auth, uploads, or QR:
- Admin/examiner routes must verify `session('examiner_id')` is set.
- Super-admin actions must verify `$currentAdmin['is_super_admin']`.
- File uploads: validate extension + MIME, reject PHP/executable extensions, store outside public root or use signed URLs.
- QR verification: HMAC must be checked before decrypting payload. Expired tokens must be caught.
- `official_students` lookup must happen before any registration or QR generation.
- Photo paths stored in DB must never be user-controlled without sanitisation.
- Role checks: `examiner_role` can be `admin`, `superadmin`, or `examiner`. Never assume.

---

## SKILL 5 — Student Workflow

The complete student lifecycle — never break any step:

```
official_students import
        ↓
  /student/register (matric lookup)
        ↓
  /student/onboard (identity + password + ID card + selfie)
        ↓
  Admin photo approval (photo-approvals queue)
        ↓
  /student/dashboard (eligibility check)
        ↓
  Assessment eligibility (payment + photo approval + official registry)
        ↓
  /student/generate-exam-pass (QR generation)
        ↓
  Examiner scans QR (entry scan → APPROVED/DUPLICATE/REJECTED)
        ↓
  [NEW] Submission confirmation (exit scan)
        ↓
  Attendance record (CHECKED_IN → SUBMITTED → COMPLETED)
        ↓
  /student/dashboard scan history
```

Assessment types: `exam`, `test`, `makeup`. Payment rules differ per type and per setting.

---

## SKILL 6 — Admin Workflow

Admin responsibilities in order of frequency:
1. Monitor dashboard (risk alerts, pending approvals, active session metrics)
2. Student registry (import CSV, view records, trace students)
3. Photo approvals (ID card + selfie review, approve/reject/flag)
4. Assessment management (timetable: exams, tests, makeups)
5. Attendance (check-in status, submission status, anomalies)
6. QR token management (revoke, audit)
7. Examiner management (create, toggle active)
8. Reports and exports
9. Notes / internal communication
10. Settings (session, fees — not global settings, that's super admin)

Optimise for speed. Admins scan many records. Tables must be fast, filterable, and scannable.

---

## SKILL 7 — Super Admin Governance

Super admin controls things that affect the entire system:
- `system_mode` (live/demo)
- `system_name`, `institution_name`, `branding_logo`
- Feature flags: `require_photo_approval_before_qr`, `allow_payment_not_required_exams`, `attendance_tracking_enabled`, etc.
- Session management (activate/close exam sessions)
- Demo data purge
- Danger zone (clear live data)
- Audit retention policy

Every super-admin setting must either work end-to-end or be hidden. No placeholder controls.

---

## SKILL 8 — Regression Prevention

Protocol before any change:
1. Read the current implementation in full.
2. Identify all callers/dependents (grep for method name, route name, view variable).
3. Make the change.
4. Run `php artisan test` — all 261 tests must pass.
5. Run `php artisan view:cache` — zero compilation errors.
6. Check that no unrelated behaviour changed.

Never skip step 4. A green test suite is the minimum bar.

---

## SKILL 9 — Token Efficiency

- Read files once, remember the content, edit precisely.
- Use `Edit` with minimal `old_string` context, not full-file rewrites.
- Grep before reading — find the exact line range needed.
- Spawn `Explore` agents for broad searches, not inline grep loops.
- Do not generate boilerplate comments or docblocks.
- Do not narrate reasoning in code comments.
- Batch independent tool calls in a single message.

---

## SKILL 10 — Final Audit

Before marking any task complete, verify:

| Area | Check |
|------|-------|
| Routes | All referenced routes exist (`php artisan route:list`) |
| Controllers | No undefined variable access, no missing method calls |
| Blade | `php artisan view:cache` succeeds |
| Database | Migrations cover all new columns referenced in code |
| Storage | Upload paths exist and are writable |
| UI | Responsive at 390px and 1280px |
| Permissions | Role checks in place for every sensitive action |
| Auth | Session guards present on all protected routes |
| Tests | `php artisan test` — 0 failures |
| Settings | No dead controls visible in UI |
| Media | Photos/ID cards renderable by admin |

---

## SKILL 11 — Implementation Summary

**Every response that makes code changes must end with this block:**

```
---
## Implementation Summary

**Changed:** [one-line description]
**Files modified:** [list]
**Bugs fixed:** [list or "none"]
**UI improvements:** [list or "none"]
**Logic improvements:** [list or "none"]
**Remaining known issues:** [list or "none"]
**Suggested next step:** [one sentence]
```

No exceptions. If a response makes no code changes, omit the block.

---

## Key File Map

```
app/
  Http/Controllers/Web/
    AdminWebController.php          — all admin web routes
    ExaminerWebController.php       — examiner + admin login
    StudentWebController.php        — register/lookup/onboard
    StudentDashboardController.php  — student portal pages
  Services/
    ExamPassService.php             — QR generation + payment validation
    RegistrationService.php         — student registration from official_students
    MockSISService.php              — mock SIS lookups (demo only)
    RemitaService.php               — payment verification
    QrTokenService.php              — token create/verify
    CryptoService.php               — AES encryption + HMAC
    RiskIntelligenceService.php     — dashboard risk metrics
    StudentRegistryImportService.php — CSV import pipeline
  Support/
    Branding.php                    — logo URL, system name, institution name
    SystemMode.php                  — live/demo detection + demo data purge
    DepartmentFees.php              — fee lookup + demo mode flag
    MatricNumber.php                — matric format parsing/validation

resources/views/
  layouts/
    portal.blade.php                — base HTML shell
    admin-control.blade.php         — admin sidebar layout
    examiner-portal.blade.php       — examiner layout
    student-portal.blade.php        — student portal layout
  admin/
    dashboard.blade.php
    settings/index.blade.php
    student-registry/index.blade.php
    photo-approvals/index.blade.php
    intelligence/index.blade.php
    attendance/index.blade.php
  student/
    dashboard.blade.php
    register.blade.php              — step 1: matric lookup
    onboard.blade.php               — step 2: identity + ID card + selfie
    timetable.blade.php
    generate-exam-pass.blade.php

database/
  migrations/                       — standard Laravel migrations
  seeders/
    MockSISSeeder.php               — must always run (no mode guard)
    DatabaseSeeder.php              — calls all seeders

tests/Feature/                      — 261 tests, all must pass
```

---

## Known Incomplete Features (as of 2026-07-02)

1. **Submission/exit scan** — entry scan exists; exit/submission confirmation not built for QR-based exit (examiner attendance page has manual "Mark Submitted" which covers this workflow).

**Fixed across sessions (no longer incomplete):**
- Student show dual-photo: selfie + ID card now rendered separately in "Verification Media" section.
- Payment logic: tests/makeups were already correct (never require RRR regardless of setting).
- Dead settings controls: removed `scanner_server_verification_required`, `qr_single_use_enforced`, `auto_flag_unverified_before_exam`, `mark_attendance_on_qr_scan`, `audit_logging_enabled`, `audit_retain_days` from UI; wired `attendance_tracking_enabled`, `require_id_card_upload`, `photo_resubmit_allowed`, `enable_submission_scan`.
- Risk Intelligence: now surfaces checked-in-not-submitted, pending photo approvals, inactive examiners, unregistered students in live operational alerts panel.
- UI: all emoji/symbol characters removed from views; design system chips used throughout.
- Attendance page (`/admin/attendance`) — fully implemented with stats, progress bar, and per-session/timetable filtering.
- Live mode indicator — green "Live Mode" pill shown in admin sidebar brand section when SystemMode::isLive().
- TimetableSeeder now guarded with SystemMode::isDemo() check to prevent demo data insertion in production.
- Photo system: student profile now shows per-document upload status (selfie on file / ID card on file) separately.
- Examiner dashboard, timetable preview tables, notifications view: all hardcoded hex colors replaced with design tokens.
