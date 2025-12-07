# Video-to-MP3 Converter Microservices

A microservices-based system for converting video files to MP3 audio, built with Laravel.

## Architecture Overview

```
┌────────────┐     ┌─────────────┐     ┌──────────────┐
│   Client   │────▶│   Gateway   │────▶│ Auth Service │
└────────────┘     │   Service   │     └──────────────┘
                   └──────┬──────┘            │
                          │                   ▼
                          │            ┌──────────────┐
                          ├───────────▶│    MySQL     │
                          │            └──────────────┘
                          ▼
                   ┌──────────────┐
                   │   MongoDB    │◀────────────────────┐
                   │   GridFS     │                     │
                   └──────────────┘                     │
                          │                             │
                          ▼                             │
                   ┌──────────────┐     ┌───────────────┴───┐
                   │   RabbitMQ   │────▶│ Converter Service │
                   │ (video queue)│     └───────────────────┘
                   └──────────────┘
```

### Services

| Service     | Port  | Description                                      |
|-------------|-------|--------------------------------------------------|
| Gateway     | 8080  | API entry point, handles uploads/downloads       |
| Auth        | 5000  | User authentication and token management         |
| Converter   | -     | Background worker for video-to-MP3 conversion    |
| RabbitMQ    | 5672  | Message broker (Management UI: 15672)            |
| MongoDB     | 27017 | File storage via GridFS                          |
| MySQL       | 3306  | User data and tokens                             |

## Prerequisites

- PHP 8.2+
- Composer
- Docker & Docker Compose
- FFmpeg (for local converter development)
- MySQL 8.0+
- MongoDB 7.0+

## Quick Start with Docker

The fastest way to run the entire system:

```bash
# 1. Start infrastructure services
docker run -d --name mongodb -p 27017:27017 mongo:7
docker run -d --name rabbitmq -p 5672:5672 -p 15672:15672 \
  -e RABBITMQ_DEFAULT_USER=guest \
  -e RABBITMQ_DEFAULT_PASS=guest \
  rabbitmq:3-management
docker run -d --name mysql -p 3306:3306 \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=microservices \
  mysql:8

# 2. Build and run services
cd auth && docker build -t auth-service . && \
  docker run -d --name auth -p 5000:8000 \
    -e DB_HOST=host.docker.internal \
    -e DB_DATABASE=microservices \
    -e DB_PASSWORD=secret \
    auth-service

cd ../gateway && docker build -t gateway-service . && \
  docker run -d --name gateway -p 8080:80 \
    -e AUTH_SVC_ADDRESS=host.docker.internal:5000 \
    -e RABBITMQ_HOST=host.docker.internal \
    -e MONGO_URI=mongodb://host.docker.internal:27017 \
    gateway-service

cd ../converter && docker build -t converter-service . && \
  docker run -d --name converter \
    -e RABBITMQ_HOST=host.docker.internal \
    -e MONGODB_HOST=host.docker.internal \
    converter-service
```

## Local Development Setup

### 1. Start Infrastructure Services

```bash
# MongoDB
docker run -d --name mongodb -p 27017:27017 mongo:7

# RabbitMQ
docker run -d --name rabbitmq -p 5672:5672 -p 15672:15672 \
  -e RABBITMQ_DEFAULT_USER=guest \
  -e RABBITMQ_DEFAULT_PASS=guest \
  rabbitmq:3-management

# MySQL
docker run -d --name mysql -p 3306:3306 \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=microservices \
  mysql:8
```

### 2. Auth Service

```bash
cd auth
composer install
cp .env.example .env

# Edit .env with your MySQL credentials:
# DB_HOST=127.0.0.1
# DB_DATABASE=microservices
# DB_USERNAME=root
# DB_PASSWORD=secret

php artisan key:generate
php artisan migrate
php artisan serve --port=5000
```

### 3. Gateway Service

```bash
cd gateway
composer install
cp .env.example .env

# Edit .env:
# AUTH_SVC_ADDRESS=127.0.0.1:5000
# RABBITMQ_HOST=127.0.0.1
# RABBITMQ_USER=guest
# RABBITMQ_PASSWORD=guest
# MONGO_URI=mongodb://127.0.0.1:27017
# MONGO_DATABASE=mp3converter

php artisan key:generate
php artisan serve --port=8080
```

### 4. Converter Service

```bash
cd converter
composer install
cp .env.example .env

# Edit .env:
# RABBITMQ_HOST=127.0.0.1
# RABBITMQ_USER=guest
# RABBITMQ_PASSWORD=guest
# MONGODB_HOST=127.0.0.1

# Ensure FFmpeg is installed
# macOS: brew install ffmpeg
# Ubuntu: sudo apt install ffmpeg
# Windows: Download from https://ffmpeg.org/download.html

php artisan converter:consume
```

## API Usage

### Register a User

```bash
curl -X POST http://localhost:8080/register \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password123"}'
```

### Login

```bash
# Returns a token
curl -X POST http://localhost:8080/login \
  -H "Authorization: Basic $(echo -n 'user@example.com:password123' | base64)"
```

### Upload Video for Conversion

```bash
curl -X POST http://localhost:8080/upload \
  -H "Authorization: Bearer <your-token>" \
  -F "file=@video.mp4"
```

Response:
```json
{
  "message": "success! Video uploaded and queued for conversion.",
  "video_fid": "507f1f77bcf86cd799439011"
}
```

### Download Converted MP3

```bash
curl -O http://localhost:8080/download?fid=<mp3_fid> \
  -H "Authorization: Bearer <your-token>"
```

## Project Structure

```
.
├── auth/                   # Authentication service
│   ├── app/
│   │   ├── Http/Controllers/AuthController.php
│   │   └── Models/User.php
│   ├── routes/api.php
│   └── Dockerfile
│
├── gateway/                # API Gateway service
│   ├── app/
│   │   ├── Http/Controllers/GatewayController.php
│   │   ├── Http/Middleware/ValidateToken.php
│   │   └── Services/
│   │       ├── AuthService.php
│   │       ├── RabbitMQService.php
│   │       └── StorageService.php
│   ├── routes/api.php
│   └── Dockerfile
│
├── converter/              # Video converter service
│   ├── app/
│   │   ├── Console/Commands/VideoConverterConsumer.php
│   │   └── Services/
│   │       ├── AudioConverterService.php
│   │       ├── MongoGridFSService.php
│   │       └── RabbitMQService.php
│   └── Dockerfile
│
├── rabbit/                 # RabbitMQ Kubernetes manifests
│   └── manifests/
│
└── docs/                   # Additional documentation
```

## Message Flow

1. **Upload**: Client uploads video → Gateway stores in GridFS → publishes to `video` queue
2. **Convert**: Converter consumes from `video` queue → downloads video → converts to MP3 → stores in GridFS → publishes to `mp3` queue
3. **Download**: Client requests MP3 → Gateway streams from GridFS

## Environment Variables Reference

### Auth Service
| Variable      | Default                | Description           |
|---------------|------------------------|-----------------------|
| DB_HOST       | 127.0.0.1              | MySQL host            |
| DB_DATABASE   | microservices          | Database name         |
| DB_USERNAME   | root                   | Database user         |
| DB_PASSWORD   | -                      | Database password     |

### Gateway Service
| Variable          | Default                         | Description              |
|-------------------|---------------------------------|--------------------------|
| AUTH_SVC_ADDRESS  | auth:5000                       | Auth service address     |
| RABBITMQ_HOST     | rabbitmq                        | RabbitMQ host            |
| RABBITMQ_PORT     | 5672                            | RabbitMQ port            |
| RABBITMQ_USER     | guest                           | RabbitMQ username        |
| RABBITMQ_PASSWORD | guest                           | RabbitMQ password        |
| MONGO_URI         | mongodb://localhost:27017       | MongoDB connection URI   |
| MONGO_DATABASE    | mp3converter                    | MongoDB database name    |

### Converter Service
| Variable          | Default               | Description                |
|-------------------|-----------------------|----------------------------|
| RABBITMQ_HOST     | rabbitmq              | RabbitMQ host              |
| RABBITMQ_PORT     | 5672                  | RabbitMQ port              |
| RABBITMQ_USER     | guest                 | RabbitMQ username          |
| RABBITMQ_PASSWORD | guest                 | RabbitMQ password          |
| MONGODB_HOST      | localhost             | MongoDB host               |
| MONGODB_PORT      | 27017                 | MongoDB port               |
| VIDEO_QUEUE       | video                 | Input queue name           |
| MP3_QUEUE         | mp3                   | Output queue name          |
| FFMPEG_PATH       | /usr/bin/ffmpeg       | Path to FFmpeg binary      |

## Monitoring

- **RabbitMQ Management**: http://localhost:15672 (guest/guest)
- **MongoDB**: Connect via MongoDB Compass at `mongodb://localhost:27017`

## Troubleshooting

### Services can't connect to each other
- When running locally, use `127.0.0.1` instead of `localhost`
- When running in Docker, use `host.docker.internal` to reach host services

### Converter not processing videos
- Check RabbitMQ for messages in the `video` queue
- Ensure FFmpeg is installed: `ffmpeg -version`
- Check converter logs: `php artisan converter:consume`

### Token validation fails
- Ensure Auth service is running on port 5000
- Check `AUTH_SVC_ADDRESS` in Gateway's `.env`

## Further Documentation

- [Local Development Guide](docs/LOCAL_DEVELOPMENT.md)
- [Docker Containerization](docs/DOCKER_CONTAINERIZATION.md)
- [Kubernetes Architecture](docs/KUBERNETES_ARCHITECTURE.md)
- [System Design](docs/SYSTEM_DESIGN.md)
