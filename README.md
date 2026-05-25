# CERNIX — Secure Exam Access & Verification System

> **Important:** CERNIX is a final year academic project demonstration. Payment verification uses Remita's demo environment — no real fees are collected. Student identity verification uses a simulated SIS. This system is not connected to any institution's live payment or student records system.

CERNIX is a Laravel-based examination access and verification system for Adekunle Ajasin University project work. It links student identity, school-fee/payment status, timetable context, and a server-verifiable QR exam pass so exam access can be checked quickly and consistently at the venue.

Students register through a guided portal, select their faculty, department, level, and student number, and CERNIX generates the full matric number from the configured Faculty of Computing code map. The system validates the generated record, confirms the required department fee/payment state, and issues a one-time QR exam pass.

Examiners use a separate scanner portal to verify QR passes at the exam entrance. Admin and Super Admin users monitor students, payments, timetable entries, scan logs, audit activity, notes/notifications, settings, and the Python-assisted Risk Intelligence dashboard.

Laravel/PHP remains the main web application. The optional Python module only analyzes exported operational logs and produces risk reports for admin decision support.

## Problem Statement

Manual exam access checks can lead to slow queues, copied slips, weak payment-clearance checks, duplicated access passes, and limited auditability. CERNIX addresses these problems by combining:

- Controlled student registration and generated matric validation.
- Department-based fee amount checks.
- Server-side QR verification and one-time token lifecycle control.
- Examiner scan decisions with audit logs.
- Admin/Super Admin oversight and risk intelligence.

## Core Features

### Student Portal

- Guided exam registration at `/student/register`.
- Automatic matric generation from level, Faculty of Computing, department, and last three student-number digits.
- Department-based school-fee amount display.
- Demo/testing payment references only when demo mode is enabled.
- QR Exam Access ID and print-friendly exam pass.
- Student dashboard, profile, timetable, payment, instructions, scan detail, and notifications pages.

### Examiner Portal

- Examiner-only login at `/examiner/login`.
- Minimal live QR scanner at `/examiner/dashboard`.
- Server-controlled verification results: approved, rejected, or duplicate.
- Scan history, student records, today’s exams, audit trail, and examiner notifications.
- Admin and Super Admin accounts are not allowed into the Examiner portal.

### Admin Portal

- Admin login at `/admin/login`.
- Dashboard for operational monitoring.
- Student, payment, timetable, examiner, scan log, activity/audit, notes, notification, and student trace views.
- Admin Settings with role-sensitive controls.
- Admin Notes with visibility support for internal, student, examiner, or both-visible notes.
- Risk Intelligence page at `/admin/intelligence`.

### Super Admin

- Uses the Admin portal, not the Examiner portal.
- Can access system-level controls exposed by the current app, including settings, fee mapping, session controls, examiner/admin management, role-sensitive operations, audit views, and intelligence reporting.
- Regular Admin users have a more limited operational view.

### Python Intelligence Module

- Located at `python_services/risk_analyzer/`.
- Analyzes exported scan/payment/audit-style JSON data.
- Produces risk scoring, suspicious student/examiner/device/IP findings, summary observations, recommendations, JSON reports, and optional HTML reports.
- Feeds the Admin Risk Intelligence page when a generated report exists.
- Does not handle authentication, QR verification, payment verification, cryptographic secrets, scanner verification, or token lifecycle logic.

## Tech Stack

- Laravel 11 / PHP
- Blade templates
- PostgreSQL for Render deployment, SQLite/local database support where configured
- Vite, JavaScript, and browser camera APIs for the scanner UI
- QR generation/scanning libraries already bundled through the Laravel/frontend stack
- Python standard library for offline risk analysis
- Docker and Render deployment files

## Major Routes

- `/` — public homepage
- `/documentation` — project documentation page
- `/student/register` — student exam registration
- `/student/dashboard` — student portal overview
- `/student/exam-access-id` and `/student/exam-pass` — QR exam pass views
- `/examiner/login` and `/examiner/dashboard` — examiner portal and scanner
- `/admin/login` and `/admin/dashboard` — admin portal
- `/admin/intelligence` — admin risk intelligence
- `/admin/settings` — admin settings

## Demo Mode

CERNIX has a demo/testing mode for academic and local testing environments. Demo payment references are accepted only when demo mode is active through the environment. In real production, keep demo mode disabled.

Do not publish real admin, examiner, database, Remita, or application credentials in public documentation. Demo users and passwords should be configured privately for the deployment environment.

## Local Setup

```bash
git clone <repository-url>
cd cernix-exam-verify
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure the database values in `.env`, then run:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

For a fresh local demo reset, use only project-provided commands and seeders. Do not commit `.env` or generated private storage reports.

## Python Intelligence Module

Run the sample analyzer:

```bash
python python_services/risk_analyzer/analyze.py
```

Export safe Laravel scan data:

```bash
php artisan cernix:export-risk-data
```

Generate a report for the Admin Intelligence page:

```bash
php artisan cernix:run-risk-analysis
```

Reports are written under `storage/app/risk-analysis/`. The web UI reads the JSON report safely. If the Python report is missing, `/admin/intelligence` still shows a live Laravel summary from current database records.

## Testing

```bash
php artisan test
npm run build
python python_services/risk_analyzer/analyze.py
```

If Playwright dependencies are installed:

```bash
npx playwright test --headed --workers=1
```

## Render Deployment

CERNIX is prepared for Render Docker deployment.

Important files:

- `Dockerfile`
- `render.yaml`
- `scripts/render-start.sh`
- `docs/render-deployment.md`

Set production environment variables in Render, not in the repository. Use:

- `APP_ENV=production`
- `APP_DEBUG=false`
- PostgreSQL connection through Render’s database URL variable
- `CERNIX_DEMO_MODE=false` for real production
- Remita and cryptographic keys stored only as private environment variables

The start script runs migrations, optional safe seeders, caches Laravel config/routes/views, and starts Laravel on Render’s assigned port.

## Security Notes

- Student, Examiner, Admin, and Super Admin portals are separated server-side.
- QR verification remains server-controlled and one-time-use.
- Audit logs record important scan and admin activity.
- Sensitive values must live in environment variables.
- Do not commit `.env`, real Remita keys, application keys, database URLs, QR payload internals, or passwords.
- Demo mode must remain disabled for real production use.

## Project Media

Project media and team/context images are documentation assets only. They are not used as student identity, passport, verification, or scanner data.

Demo passport images are local mock assets used for controlled testing and are not real university records.

## Academic Note

CERNIX is an academic/project system demonstrating secure exam access workflows, role-based portals, QR verification, auditability, and lightweight risk intelligence.
