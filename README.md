# UNIMAR Portfolios — API

REST API backend for the UNIMAR graphic design portfolio builder platform.

## Stack

- **Framework:** Laravel 13 (PHP 8.3+)
- **Database:** PostgreSQL 18
- **Server:** FrankenPHP
- **Auth:** Laravel Sanctum (token-based)
- **Infrastructure:** Docker / Docker Compose

## Requirements

- Docker & Docker Compose

## Getting Started

1. Copy the environment file and adjust values as needed:

```bash
cp .env.example .env
```

2. Build and start the containers:

```bash
docker compose up -d --build
```

3. Generate the application key:

```bash
docker compose exec app php artisan key:generate
```

4. Run migrations:

```bash
docker compose exec app php artisan migrate
```

## API

All endpoints are prefixed with `/api`. A health check is available at:

```
GET /api/health
```

## Development Rules

- All `php artisan` and `composer` commands must be run inside the container:
  ```bash
  docker compose exec app php artisan <command>
  ```
- All models use UUID primary keys (`$table->uuid('id')->primary()`).
- `jsonb` columns are used for storing Craft.js builder state and user settings.
- All endpoints return JSON responses.
