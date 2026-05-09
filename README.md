# SchoolAide Platform

A multi-tenant Student Services Management Platform built as a white-label SaaS for school organizations. Each school operates as an isolated tenant sharing a single MySQL database, with role-based access control, approval workflows, Excel imports, and comprehensive audit logging.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.2 |
| Auth | Laravel Passport (personal access tokens) |
| Authorization | Spatie Laravel Permission (tenant-scoped roles) |
| Database | MySQL 8.0 |
| Queue / Cache | Redis 7 |
| Excel Import | Maatwebsite Excel |
| Testing | Pest 3 |
| Static Analysis | PHPStan level 8, Laravel Pint (PSR-12) |

## Quick Start

### Prerequisites
- XAMPP 8.2+ (PHP 8.2, MySQL 8.0)
- Composer 2.x
- Node.js 18+
- Redis 7 ([Windows build](https://github.com/tporadowski/redis/releases))

### Setup

```bash
cd schoolaide-platform/laravel
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` — set `DB_DATABASE`, `CACHE_DRIVER=redis`, `QUEUE_CONNECTION=redis`.

```bash
php artisan migrate
php artisan db:seed          # creates two demo tenants
php artisan passport:install
```

Start all services (single command via Composer script):

```bash
composer run dev
# Laravel API  → http://localhost:8000
```

Or start each service manually:

```bash
php artisan serve
php artisan queue:work redis --queue=imports,default
```

### Demo Credentials

| Tenant | Email | Password | Role |
|--------|-------|----------|------|
| `greenfield` | admin@greenfield.com | password | Admin |
| `riverside` | admin@riverside.com | password | Admin |

All API requests require the `X-Tenant: {slug}` header.

## Testing

```bash
cd schoolaide-platform/laravel

# Create a dedicated test database
mysql -u root -e "CREATE DATABASE schoolaide_platform_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Add to `.env.testing`:
```env
DB_DATABASE=schoolaide_platform_test
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
```

```bash
# Run all tests
php artisan test

# Or via Pest directly
vendor/bin/pest

# Specific test files
vendor/bin/pest tests/Feature/MultiTenantIsolationTest.php
vendor/bin/pest tests/Feature/ServiceRequestApprovalTest.php

# With coverage
vendor/bin/pest --coverage
```

## Key Test Scenarios

- **Multi-tenant isolation** — staff cannot read or modify another tenant's data
- **Concurrent approvals** — first writer wins; second gets HTTP 409
- **Authorization** — only assigned staff or admin can approve a request
- **Import validation** — invalid rows are skipped and logged with reasons
- **Audit immutability** — audit log records cannot be updated or deleted

## Documentation

| Document | Description |
|----------|-------------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Multi-tenancy, authorization model, concurrency strategy, caching, trade-offs |
| [docs/API.md](docs/API.md) | All endpoints with request/response examples |
| [docs/DATABASE_SCHEMA.sql](docs/DATABASE_SCHEMA.sql) | Full table definitions, indexes, and foreign keys |
| [docs/SETUP.md](docs/SETUP.md) | Detailed local setup, queue configuration, troubleshooting |

## Known Issues

- **Redis required** — tag-based cache invalidation (`Cache::tags()`) is not supported by the file or array drivers; Redis must be running for the application to function correctly outside of test mode.
- **Passport keys** — `php artisan passport:install` must be run after first migration; missing keys produce a 500 on any authenticated endpoint.
- **Spatie permission cache** — after seeding or changing roles, run `php artisan permission:cache-reset` if permission checks return unexpected results.
- **Queue worker** — Excel import processing is asynchronous; without a running queue worker (`php artisan queue:work`) import jobs will stay in `pending` status indefinitely.
- **No Docker Compose** — the project uses a local XAMPP setup. Docker support can be added via `laravel/sail` (already in `require-dev`).
