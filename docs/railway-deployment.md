# Railway Deployment (Legacy)

Railway was evaluated during deployment preparation, but the current recommended deployment target is Render with Docker. Keep this file only as historical reference for teams that still want to adapt the app to Railway.

## 1. Push To GitHub

Commit the Railway deployment files and push the repository to GitHub:

```bash
git add railway.json .env.example docs/railway-deployment.md
git commit -m "Prepare Railway deployment"
git push
```

## 2. Create Railway Project

1. Open Railway and create a new project.
2. Choose **Deploy from GitHub repo**.
3. Select the CERNIX repository.

## 3. Add PostgreSQL

Add a PostgreSQL service in the same Railway project. Railway exposes the database URL as `Postgres.DATABASE_URL`.

## 4. Required Variables

Set the same production variables documented in `docs/render-deployment.md`, using Railway's PostgreSQL URL for the database connection. Store actual application, database, Remita, and cryptographic values in Railway only.

Generate the Laravel app key locally:

```bash
php artisan key:generate --show
```

Paste the generated value into Railway as `APP_KEY`.

## 5. Demo Mode

Production deploys should normally keep:

```env
CERNIX_DEMO_MODE=false
```

For a public demo where demo payment references should work, set:

```env
CERNIX_DEMO_MODE=true
```

Demo payment references remain demo-only. With `APP_ENV=production` and `CERNIX_DEMO_MODE=false`, demo registration shortcuts are rejected.

## 6. Public Domain

Railway services are private until a domain is generated.

1. Open the Laravel service.
2. Go to **Networking**.
3. Generate a public domain.
4. Set `APP_URL` to the generated HTTPS URL.
5. Redeploy if the app was already built with a different URL.

## 7. Build And Deploy

`railway.json` uses Nixpacks and runs:

```bash
composer install --no-dev --optimize-autoloader && npm ci && npm run build
```

Before each deploy, Railway runs:

```bash
php artisan migrate --force && php artisan db:seed --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The app starts with:

```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

## 8. Post-Deploy Smoke Test

After Railway finishes deploying, open the public HTTPS URL and test:

- `/`
- `/student/register`
- `/admin/login`
- `/admin/dashboard`
- `/admin/settings`
- `/examiner/login`
- `/examiner/dashboard`
- Student registration with a private demo payment reference if `CERNIX_DEMO_MODE=true`
- QR generation from the student dashboard
- Examiner scanner page renders
- Admin/Super Admin cannot enter the Examiner portal
- Examiner cannot enter the Admin portal

## 9. Production Notes

- Keep `APP_DEBUG=false`.
- Keep logs on `stderr` through `LOG_CHANNEL=stderr`.
- Use PostgreSQL through `DB_CONNECTION=pgsql` and `DB_URL=${{Postgres.DATABASE_URL}}`.
- Do not store real Remita or crypto secrets in the repository.
- Demo passport images are committed under `public/demo-passports/`.
- Project media images are documentation-only and are not used as student identity photos.
