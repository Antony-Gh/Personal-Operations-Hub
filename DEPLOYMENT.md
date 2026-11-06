# Personal Operations Hub — Production Deployment Guide

This guide details the recommended architecture, configuration, and step-by-step procedures to deploy the **Personal Operations Hub** to an online production environment securely and reliably.

---

## 1. Hosting Architecture & Platform Analysis

### Why Vercel is Not Recommended for this Monolith

- **Ephemeral Filesystem**: Vercel runs applications in stateless serverless lambdas. Local files—including SQLite databases (`database.sqlite`) and local session files—are wiped between executions or fragmented across isolated instances.
- **Serverless PHP Constraints**: Vercel has no first-party PHP runtime. Running Laravel on Vercel requires community wrappers (e.g. `vercel-php`), adding cold-start latency, memory overhead, and maintenance fragility.
- **Inertia.js Monolith Model**: Inertia is designed for tightly coupled full-stack applications where Laravel manages server-side routing, authentication, and SSR/Inertia props. Splitting the app solely to fit Vercel would necessitate rebuilding the backend as a decoupled REST/GraphQL API.

### Recommended Production Platforms

For a seamless, reliable deployment of this Laravel + Vue 3/Inertia application:

1. **Platform as a Service (PaaS)** *(Recommended for simplicity & low maintenance)*:
   - **Render** (`render.com`): Web Service with persistent PostgreSQL managed database.
   - **Railway** (`railway.app`): Automatic Docker/Nixpacks detection with managed Postgres.
   - **Fly.io** (`fly.io`): Global containerized deployment with LiteFS or Postgres.

2. **Managed Cloud Server (VPS)** *(Recommended for full control & maximum performance)*:
   - **Laravel Forge** + **Hetzner / DigitalOcean / AWS Lightsail**: Official Laravel deployment automation, automated SSL, zero-downtime deployment, background queues, and scheduled tasks.

---

## 2. Production Database Strategy

- **Local Development**: SQLite (`database/database.sqlite`) remains the default for frictionless local hacking and automated tests.
- **Production Online**: Use a persistent managed database service:
  - **PostgreSQL 14+** *(Recommended)*: High concurrency, robust ACID compliance, and excellent JSON/UUID support.
  - **MySQL 8.0+ / MariaDB**: Fully supported via native Laravel Eloquent migrations.

All existing migrations in `database/migrations/` use standard Laravel Schema Builders compatible with PostgreSQL, MySQL, and SQLite without modification.

---

## 3. Environment Variables Reference

Configure these environment variables on your production host (refer to `.env.example`):

| Variable | Recommended Production Value | Description |
| :--- | :--- | :--- |
| `APP_NAME` | `"Personal Operations Hub"` | Workspace title |
| `APP_ENV` | `production` | Enforces production optimizations |
| `APP_DEBUG` | `false` | **Critical**: Prevents leaking stack traces & secrets |
| `APP_KEY` | *(Generated via `php artisan key:generate`)* | 32-character encryption key |
| `APP_URL` | `https://your-domain.com` | Fully qualified production HTTPS URL |
| `ALLOW_REGISTRATION` | `false` | Blocks `/register` server-side (HTTP 403) for private single-user hubs |
| `DB_CONNECTION` | `pgsql` *(or `mysql`)* | Production database driver |
| `DB_HOST` | `postgres.your-host.internal` | Managed database host |
| `DB_PORT` | `5432` | Database port (5432 for Postgres, 3306 for MySQL) |
| `DB_DATABASE` | `personal_operations_hub` | Database name |
| `DB_USERNAME` | `poh_user` | Database user |
| `DB_PASSWORD` | `[SECURE_DB_PASSWORD]` | Database password |
| `SESSION_DRIVER` | `database` | Stores sessions in DB (persists across server restarts) |
| `SESSION_SECURE_COOKIE` | `true` | Ensures cookies are only transmitted over HTTPS |
| `SESSION_ENCRYPT` | `true` | Encrypts session payloads |
| `CACHE_STORE` | `database` *(or `redis`)* | Application cache driver |
| `QUEUE_CONNECTION` | `database` | Background job driver |
| `LOG_CHANNEL` | `stack` | Production logging pipeline |
| `LOG_LEVEL` | `info` *(or `error`)* | Suppresses noisy debug logs |

---

## 4. Step-by-Step Deployment Procedure

### Step 1: Clone and Install Dependencies

```bash
# 1. Install production PHP dependencies (excluding dev tools)
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Install Node dependencies and compile frontend assets
npm ci
npm run build
```

> **Note on Frontend Assets**: `npm run build` generates pre-compiled, hashed JavaScript and CSS bundles into `public/build/`. In production, Laravel serves these static assets directly; the Vite dev server (`npm run dev`) is **not** run in production.

### Step 2: Database Initialization & Safe Migrations

```bash
# Run non-destructive database migrations
php artisan migrate --force
```

*(If setting up a fresh database for the first time, you may seed the initial administrator account and baseline categories using `php artisan db:seed --force`.)*

### Step 3: Performance Cache Warmup

Compile and cache routes, configuration, and Blade views for sub-millisecond response times:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Step 4: Storage Symlink

```bash
php artisan storage:link
```

---

## 5. Health Check Endpoint

Laravel provides a built-in health check endpoint:

- **URL**: `https://your-domain.com/up`
- **Expected Status**: `HTTP 200 OK`

Configure your cloud provider's health check (e.g. Render Health Check Path or AWS Target Group Health Check) to point to `/up`.

---

## 6. Security Checklist

- [x] `APP_DEBUG=false` in production.
- [x] `ALLOW_REGISTRATION=false` enforced server-side.
- [x] Reverse-proxy SSL headers trusted via `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`.
- [x] CSRF protection enabled across all state-mutating requests (`POST`, `PUT`, `DELETE`).
- [x] Multi-tenant policy checks applied to all user-owned records (Tasks, Categories, Projects, Milestones, Daily Plans, Habits, Reviews).
- [x] Session cookies set to `Secure`, `HttpOnly`, and `SameSite=Lax`.

---

## 7. Rollback & Disaster Recovery

### Safe Database Backups

- Regularly download automated JSON snapshots or raw database archives via `/settings/data`.
- For PostgreSQL / MySQL, set up daily automated snapshots through your cloud provider (e.g., Render automated backups or AWS RDS snapshots).

### Rolling Back a Release

If an issue occurs after deploying a new release:

1. Revert to the previous Git release commit.
2. Recompile assets: `npm ci && npm run build`.
3. If database migrations need to be rolled back:

   ```bash
   php artisan migrate:rollback --step=1 --force
   ```

4. Re-cache configuration:

   ```bash
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
