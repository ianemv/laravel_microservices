# Auth Service

Authentication microservice for the video-to-MP3 converter system.

## Overview

The Auth Service handles user registration, authentication, and token validation. It uses Laravel Sanctum for API token management and serves as the identity provider for the entire microservices ecosystem.

## System Integration

```
┌─────────────┐      ┌──────────────┐
│   Gateway   │─────▶│ Auth Service │
│   Service   │◀─────│              │
└─────────────┘      └──────────────┘
                            │
                            ▼
                     ┌──────────────┐
                     │    MySQL     │
                     │   Database   │
                     └──────────────┘
```

- **Gateway Service** proxies login/register requests and validates tokens via this service
- **MySQL** stores user credentials and Sanctum tokens

## API Endpoints

| Method | Endpoint    | Description                          | Auth Required |
|--------|-------------|--------------------------------------|---------------|
| POST   | `/register` | Create new user account              | No            |
| POST   | `/login`    | Authenticate with Basic Auth         | Basic Auth    |
| POST   | `/validate` | Validate token and return user info  | Bearer Token  |
| GET    | `/health`   | Health check endpoint                | No            |

## Authentication Flow

1. User registers via `/register` with email/password
2. User logs in via `/login` with Basic Auth header
3. Service returns a Sanctum token
4. Token is used for subsequent requests to protected endpoints
5. Gateway validates tokens via `/validate` before processing requests

## Environment Variables

| Variable       | Description                    |
|----------------|--------------------------------|
| `DB_HOST`      | MySQL host                     |
| `DB_DATABASE`  | Database name                  |
| `DB_USERNAME`  | Database user                  |
| `DB_PASSWORD`  | Database password              |
| `APP_KEY`      | Laravel application key        |

## Running Locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=5000
```

## Docker

```bash
docker build -t auth-service .
docker run -p 5000:5000 auth-service
```
