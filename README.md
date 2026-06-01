# CERNIX - Secure Exam Access & Verification System

> **Important:** CERNIX is an academic project demonstration. Payment verification uses demo configuration unless an institution connects its official payment account. Student identity verification uses a simulated SIS. Do not use this repository as a live institutional records system without a security and infrastructure review.

CERNIX is a Laravel 11 exam-access application for student registration, fee verification, QR exam passes, examiner scanning, administrative review, and audit logging. Laravel remains the primary application. The optional Python intelligence module analyzes exported operational logs for deeper review.

## Main Portals

- Student registration and exam dashboard: `/student/register`
- Examiner scanner: `/examiner/login`
- Admin and Super Admin control center: `/admin/login`
- Risk intelligence: `/admin/intelligence`
- Project documentation: `/documentation`

## Stack

- Laravel 11, PHP, Blade, and Vite
- PostgreSQL on Render
- SQLite in the automated test environment
- Docker deployment through `Dockerfile`, `render.yaml`, and `scripts/render-start.sh`
- Optional Python risk analyzer under `python_services/risk_analyzer/`

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Use a local database for development. Demo accounts and mock SIS records are created by insert-only seeders. Credentials remain private to the local or demonstration environment.

## Production Persistence Rule

Runtime records must never be deleted or recreated during startup or routine deploys. This includes:

- student registrations
- payment records
- QR exam passes
- verification logs and scan history
- audit logs
- admin notes
- timetable records
- settings

Render startup runs:

```bash
php artisan migrate --force
php artisan cernix:ensure-baseline-data
```

The baseline-data command keeps registration departments available, ensures one active
session when none is open, and repairs demo staff login rows when required. It does not
rotate keys for existing sessions or touch runtime activity. Full demo seeding remains
disabled by default. For a first provisioning deploy only, set:

```env
CERNIX_SEED_ON_BOOT=true
```

After the initial default records exist, restore:

```env
CERNIX_SEED_ON_BOOT=false
```

Production startup refuses to run unless `DB_CONNECTION=pgsql` and a Render PostgreSQL URL is configured through `DATABASE_URL` or `DB_URL`. The local reset command is blocked in production unless a supervised recovery explicitly sets `CERNIX_ALLOW_PRODUCTION_RESET=true` and uses `--force`.

See [docs/render-deployment.md](docs/render-deployment.md) for the full Render checklist.

## Render Storage Note

Render containers have an ephemeral local filesystem. CERNIX currently uses committed demo passport images and regenerable thumbnail caches. Before accepting real student-uploaded photos, configure persistent S3-compatible object storage and set the appropriate filesystem disk.

## Python Intelligence Module

The Python module is optional backend support. It analyzes exported logs and does not handle authentication, payment verification, QR verification, or application secrets.

```bash
python python_services/risk_analyzer/analyze.py
php artisan cernix:export-risk-data
php artisan cernix:run-risk-analysis
```

## Validation

```bash
npm run build
php artisan test
python python_services/risk_analyzer/analyze.py
```

## Security Notes

- Keep `APP_DEBUG=false` in production.
- Never commit `.env`, payment keys, database URLs, passwords, or cryptographic keys.
- Keep `CERNIX_DEMO_MODE=false` for real production deployments.
- Use the institution's official payment-provider account before collecting real fees.
- Treat audit and scan records as append-only operational history.
