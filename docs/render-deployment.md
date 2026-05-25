# Render Deployment

This guide deploys CERNIX as a Docker Web Service on Render with Render PostgreSQL.

## 1. Confirm The Laravel Root

The Docker files belong in the Laravel app root: the folder that contains `artisan`, `composer.json`, `package.json`, `resources/`, `routes/`, and `app/`.

If your GitHub repository wraps the app inside a `cernix/` subfolder, set the Render service root directory to `cernix` in the dashboard, or keep `render.yaml` at the repository root and adjust `dockerContext` and `dockerfilePath` to point into that folder.

## 2. Deploy With Docker

1. Push the repository to GitHub.
2. In Render, create a new **Web Service**.
3. Choose the GitHub repository.
4. Select **Docker** as the runtime.
5. Use `Dockerfile` from the Laravel root.
6. Set the service port through Render's `PORT` variable. The container defaults to `10000` locally.

The container starts through `scripts/render-start.sh`, which validates Render's `PORT` value before calling Laravel:

```bash
php artisan serve --host=0.0.0.0 --port="$APP_PORT"
```

## 3. PostgreSQL

Create a Render PostgreSQL database and connect it to the web service.

Render provides a PostgreSQL connection string as `DATABASE_URL`. CERNIX supports both `DATABASE_URL` and `DB_URL`, so either variable can point to the Render connection string.

## 4. Required Environment Variables

Set these on the Render Web Service. Store actual values in Render only:

- `APP_NAME` set to `CERNIX`
- `APP_ENV` set to `production`
- `APP_KEY` generated privately
- `APP_DEBUG` set to `false`
- `APP_URL` set to the Render HTTPS URL
- `LOG_CHANNEL` set to `stderr`
- `LOG_LEVEL` set to `info`
- `DB_CONNECTION` set to `pgsql`
- `DATABASE_URL` from Render PostgreSQL
- `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` set to database-backed drivers
- `FILESYSTEM_DISK` set to `public`
- `CERNIX_DEMO_MODE` set to `false` for real production
- Remita merchant/API/service/base URL values, stored privately
- CERNIX cryptographic keys, stored privately

Generate the app key locally:

```bash
php artisan key:generate --show
```

Copy that value into Render as `APP_KEY`.

After Render gives you the service URL, set `APP_URL` to that HTTPS URL.

## 5. Demo Mode

Production defaults should keep:

```env
CERNIX_DEMO_MODE=false
```

For a public demo where demo payment references should work, manually set:

```env
CERNIX_DEMO_MODE=true
```

Do not hardcode demo mode in the repository.

## 6. Startup Behavior

`scripts/render-start.sh` runs:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan storage:link || true
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve --host=0.0.0.0 --port="$APP_PORT"
```

The seeders are idempotent. To skip seeding on a future Render deploy, set:

```env
RENDER_SKIP_SEED=true
```

## 7. Assets And HTTPS

The Docker build runs:

```bash
npm ci
npm run build
```

In production, Laravel forces generated URLs to HTTPS without hardcoding the Render URL. Set `APP_URL` to the Render HTTPS URL after the service is created.

## 8. Storage And Media

Demo student passport images are committed under:

```text
public/demo-passports/
```

Project/coursemate documentation images remain documentation-only under:

```text
public/docs/project-media/
docs/images/project-media/
```

They are not used as student identity/passport photos.

## 9. Smoke Test Checklist

Open the Render URL and test:

- `/`
- `/student/register`
- `/admin/login`
- `/admin/dashboard`
- `/admin/settings`
- `/examiner/login`
- `/examiner/dashboard`
- Student registration with a private demo payment reference if `CERNIX_DEMO_MODE=true`
- Admin/Super Admin cannot log into the Examiner portal
- Examiner cannot log into the Admin portal
- `/admin/intelligence` shows live scan-risk metrics immediately, and uses the Python-enhanced report only when that report is current

## 10. Security Notes

- Keep `APP_DEBUG=false`.
- Do not commit `.env`.
- Keep Remita and crypto keys in Render environment variables.
- Keep `CERNIX_DEMO_MODE=false` for real production.
- Do not publish demo credentials, payment references, QR internals, or environment secrets in public documentation.
