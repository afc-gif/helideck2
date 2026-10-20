# Helideck Report

Monorepo for Helideck Report, a helideck and heliport survey platform overseen by ARTSCI platform administrators. It contains a React 19, TypeScript, and Vite frontend in `frontend/`, and a Laravel 13 API in `backend/`. PostgreSQL is the production database. The project does not include official aviation requirements or sample survey questions.

## Local development

Requirements: Node.js 20.19+, npm, PHP 8.3+, Composer 2, and PostgreSQL (or SQLite for local-only work).

```sh
cd backend
composer install
cp .env.example .env   # only if backend/.env does not exist
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Set `DB_CONNECTION=sqlite` in the local, ignored `backend/.env` if PostgreSQL is not available and create `backend/database/database.sqlite`. The production configuration and Docker entrypoint require PostgreSQL and do not fall back to SQLite.

In a second terminal:

```sh
cd frontend
npm ci
npm run dev
```

Vite proxies `/api` and `/sanctum` to the local Laravel server. To use a different local API origin, set `VITE_API_BASE_URL` in `frontend/.env.local` (for example `http://127.0.0.1:8000`). This is a public URL, never a secret.

## GitHub preparation

This directory currently has no usable Git repository metadata, branches, or remote. The root `.gitignore` and both service `.dockerignore` files are ready for initialization. Review the staged file list before committing; keep all `.env` files except the placeholder `.env.example` files, database dumps, private keys, and local database files out of Git. The existing `backend/.env` contains local settings including an `APP_KEY`; it is ignored by Git and excluded from the Docker build context. No production credentials were found in application sources or the placeholder example files. GitHub CLI is installed, but its saved accounts are not authenticated because their tokens are invalid.

Initialize locally and choose repository visibility/destination yourself:

```sh
git init
git add .
git status --short
git diff --cached --stat
```

Only then commit and add a remote you have selected. No repository was created and nothing was pushed. If a credential is ever found in committed history, rotate it first; removing it from the current tree does not remove it from history. Do not rewrite shared history without an explicit decision.

## Railway monorepo services

Create a Railway project with a PostgreSQL service and two application services from the same repository. Keep the database on Railway's private network. Configure each application service to build with its Dockerfile:

| Service | Root directory | Dockerfile | Health check |
| --- | --- | --- | --- |
| Backend API | `/backend` | `Dockerfile` | `/api/health` |
| Frontend | `/frontend` | `Dockerfile` | `/healthz` |

The backend image uses PHP 8.3 Apache, installs `pdo_pgsql` and OPcache, and sets Apache's document root to Laravel's `public/`. Its entrypoint listens on Railway's injected `PORT`, caches runtime configuration, requires `APP_KEY`, `APP_ENV=production`, and `APP_DEBUG=false`, and exits if `DB_CONNECTION` is not `pgsql`. Laravel's `/up` is a basic process health route; `/api/health` checks that the configured database can answer a simple query and returns only `200 {status: ok}` or a generic `503 {status: unavailable}`.

The frontend image builds the existing Vite SPA and serves it with Nginx. Nginx listens on the injected `PORT`, serves static assets, and falls back to `index.html` for direct navigation and refreshes on React routes. Set `VITE_API_BASE_URL` as a frontend Docker build argument/environment variable to the public HTTPS URL of the backend, such as `https://api.example.com`. This value is embedded in browser code and must contain no secrets. In Railway, make sure the variable is available to the Docker build; rebuild after changing it.

### Backend Railway variables

Set these on the backend service, not in Git:

| Variable | Value |
| --- | --- |
| `APP_NAME` | `Helideck Report` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Generate with `php artisan key:generate --show` in a secure local environment and paste the result into Railway's secret variable editor. Never commit it. |
| `APP_URL` | Public HTTPS backend URL |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | Railway PostgreSQL `PGHOST` private reference |
| `DB_PORT` | Railway PostgreSQL `PGPORT` reference |
| `DB_DATABASE` | Railway PostgreSQL `PGDATABASE` reference |
| `DB_USERNAME` | Railway PostgreSQL `PGUSER` reference |
| `DB_PASSWORD` | Railway PostgreSQL `PGPASSWORD` secret reference |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_SAME_SITE` | `lax` |
| `SESSION_DOMAIN` | Shared parent domain for the custom frontend and API domains, for example `.example.com` |
| `SANCTUM_STATEFUL_DOMAINS` | Exact frontend hostname (and port if applicable), without scheme, e.g. `app.example.com` |
| `CORS_ALLOWED_ORIGINS` | Exact frontend origin including scheme, e.g. `https://app.example.com`; comma-separate additional exact origins |

Use Railway variable references from the PostgreSQL service for `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, and `PGPASSWORD`; map those values to Laravel's `DB_*` variables above. Keep the database private; do not use public proxy connection variables for the backend service. Do not configure `DATABASE_URL`/`DB_URL` unless you intentionally want Laravel's URL setting to take precedence over the individual `DB_*` values. `DB_SSLMODE` may be set to `prefer` unless your database setup requires another mode.

### Frontend Railway variables

Set `VITE_API_BASE_URL` to the backend's public HTTPS origin as a Docker build variable. It is intentionally public. Never add passwords, keys, or tokens to any `VITE_` variable.

### Cookie authentication, CORS, and domains

The app keeps its existing Sanctum session-cookie and CSRF-token authentication. The API allows credentials only from origins explicitly listed in `CORS_ALLOWED_ORIGINS`; it does not allow every origin. The frontend must send credentials, and the backend's Sanctum stateful list must include the frontend hostname.

For separately deployed services, use custom domains under the same parent domain (for example `app.example.com` and `api.example.com`) and set `SESSION_DOMAIN=.example.com`. This lets the SPA read the CSRF cookie while keeping cookie-based authentication and CSRF protection. Railway-generated service hostnames are on separate service subdomains; do not set a broad shared cookie domain for `*.up.railway.app`. Configure the custom domains and HTTPS before enabling sign-in. If you cannot use a shared parent domain, put an HTTPS same-origin reverse proxy in front of both services instead of weakening cookie or CSRF protections.

Railway terminates TLS at its proxy. Laravel trusts forwarded proxy headers so it detects the original HTTPS request and applies secure-cookie behavior. Restrict CORS and Sanctum stateful hosts to the actual production frontend/API deployment.

## Migrations and first administrator

Review each pending migration and confirm the Railway PostgreSQL service reference points to the intended production database before applying anything. Configure the backend service's Railway pre-deploy command as:

```sh
php artisan migrate --force && php artisan db:seed --force
```

Railway pre-deploy commands run with service variables and private-network access in a separate container; a failing command stops the deployment. This runs migrations before the new version receives traffic and seeds the idempotent baseline role/permission records. Review release logs and take/confirm a database backup according to the organization's release policy. The organization/membership migration intentionally refuses rollback because a user may now belong to multiple organizations; take a backup and review that migration before its first production run. Never use `migrate:fresh`, `db:wipe`, or an unreviewed database target in production. If Railway pre-deploy commands are unavailable, run the same command once from a Railway shell attached to the backend service after verifying its environment and database reference.

After the first successful migration, create the initial administrator once from an interactive backend Railway shell:

```sh
php artisan artsci:admin:create "Platform Administrator" admin@your-company.example
```

The command prompts for a hidden password and confirmation (minimum 14 characters), and refuses a second platform administrator. Do not put the password in source, a command argument, or a deployment variable. Use the authorized operator's interactive terminal and verify the command's success message. Do not run this on every deploy or restart.

## Endpoints

| URL | Purpose | Authentication |
| --- | --- | --- |
| `GET https://api.example.com/api/health` | API and database readiness | Public, generic response |
| `GET https://api.example.com/up` | Laravel process health | Public |
| `GET https://app.example.com/healthz` | Frontend container health | Public |
| `GET /sanctum/csrf-cookie` | Start CSRF-protected browser session | Public |
| `POST /api/login` | Login | Public, CSRF protected and rate limited |
| `POST /api/logout` | Logout | Authenticated session |
| `GET /api/me` | Current user | Authenticated session |
| `GET /api/organizations` | List permitted organizations | Authenticated, tenant scoped |
| `GET /api/organizations/{public_id}` | View an organization | Authenticated, other tenants receive 404 |
| `GET /api/organizations/{public_id}/members` | List organization members | Organization admin or super admin |
| `GET /api/platform/roles` | View platform roles and permissions | Super admin |

Authentication uses Sanctum's first-party session cookies and CSRF protection. The browser stores neither passwords nor bearer tokens. Organization membership is many-to-many, with a role per membership; platform super-admin assignment is separate. Laravel policies and scoped queries enforce access server-side. Membership creation and role changes are not exposed over HTTP yet, so provision the first organization members through a trusted, audited process. Configurable checklist structures exist, while checklist administration and inspections remain future work.

## Checks

From `backend/`:

```sh
php artisan test
vendor/bin/pint --format agent
```

From `frontend/`:

```sh
npm ci
npm run lint
npm run build
```

## Troubleshooting and known limits

- A database connection error usually means the backend's `DB_*` Railway references are missing, use the public rather than private host, or point to the wrong PostgreSQL service. Check variable references and logs; do not print secrets.
- If API calls fail in the browser, check HTTPS URLs, exact `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, shared custom-domain cookie domain, and `SESSION_SECURE_COOKIE=true`. CORS must include credentials and cannot use wildcard origins.
- After changing `VITE_API_BASE_URL`, rebuild/redeploy the frontend image.
- This environment has not verified a live Railway or PostgreSQL connection. The repository currently has no usable Git metadata/remote, no GitHub repository was created, and no deployment was made. `gh` availability/authentication is checked during this preparation but does not authorize repository creation or push.
- Membership management, checklist administration, and inspection workflows are not part of this deployment-readiness slice. The application still requires trusted initial organization membership provisioning until its member administration endpoints are implemented.
