# Setup Guide — SchoolAide Platform (XAMPP)

## Prerequisites

| Tool | Version |
|------|---------|
| XAMPP | 8.2+ (PHP 8.2, MySQL 8.0) |
| Composer | 2.x |
| Node.js | 18+ |
| Redis | 7.x (for queues & tag-based cache) |

> **Redis on Windows:** Download from [https://github.com/tporadowski/redis/releases](https://github.com/tporadowski/redis/releases) and run `redis-server.exe`.

---

## Laravel Backend Setup

### 1. Clone and install dependencies

```bash
cd schoolaide-platform/laravel
composer install
```

### 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=schoolaide_platform
DB_USERNAME=root
DB_PASSWORD=          # leave blank for XAMPP default

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 3. Create the database

Open XAMPP → phpMyAdmin → create database `schoolaide_platform` (utf8mb4_unicode_ci).

Or via CLI:
```bash
# With XAMPP MySQL in PATH
mysql -u root -e "CREATE DATABASE schoolaide_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. Run migrations and seed

```bash
php artisan migrate
php artisan db:seed
```

This creates two demo tenants:
- `greenfield` — `admin@greenfield.com` / `password`
- `riverside`  — `admin@riverside.com`  / `password`

### 5. Install Laravel Passport

```bash
php artisan passport:install
```

### 6. Start the development server

```bash
php artisan serve
# API available at http://localhost:8000
```

### 7. Start the queue worker (required for Excel imports)

Open a second terminal:

```bash
php artisan queue:work redis --queue=imports,default
```

---

## Frontend Setup

```bash
cd schoolaide-platform/frontend
npm install
```

Create `.env`:
```env
VITE_API_URL=http://localhost:8000/api
```

Start dev server:
```bash
npm run dev
# Frontend at http://localhost:5173
```

---

## Running Tests

```bash
cd schoolaide-platform/laravel

# Create a test database
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

# Run with Pest directly
vendor/bin/pest

# Run a specific test file
vendor/bin/pest tests/Feature/MultiTenantIsolationTest.php

# Run with coverage report
vendor/bin/pest --coverage
```

---

## Quick API Test (curl examples)

### Register a new school
```bash
curl -X POST http://localhost:8000/api/tenants/register \
  -H "Content-Type: application/json" \
  -d '{
    "organization_name": "Demo University",
    "slug": "demo",
    "admin_name": "Admin User",
    "admin_email": "admin@demo.com",
    "admin_password": "password123",
    "admin_password_confirmation": "password123"
  }'
```

### Login (use existing seeded tenant)
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant: greenfield" \
  -d '{"email":"admin@greenfield.com","password":"password"}'
```

### Use the token
```bash
TOKEN="<token from login response>"

curl http://localhost:8000/api/students \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant: greenfield"
```

---

## Code Quality

```bash
# Static analysis (PHPStan level 8)
vendor/bin/phpstan analyse

# Auto-format (Laravel Pint / PSR-12)
vendor/bin/pint
```

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| `Class "Redis" not found` | Enable `extension=redis` in `php.ini` (XAMPP) |
| Passport keys missing | Run `php artisan passport:install` |
| 404 on all API routes | Ensure `.htaccess` is present or use `php artisan serve` |
| Queue not processing | Run `php artisan queue:work` in a separate terminal |
| Spatie permission cache stale | Run `php artisan permission:cache-reset` |
