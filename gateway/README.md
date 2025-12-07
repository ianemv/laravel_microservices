# Gateway Service

API Gateway microservice that serves as the single entry point for the video-to-MP3 converter system.

## Overview

The Gateway Service handles all client requests, manages authentication by proxying to the Auth Service, orchestrates file uploads to MongoDB GridFS, and publishes conversion jobs to RabbitMQ. It acts as the facade for the entire microservices architecture.

## System Integration

```
                                    ┌──────────────┐
                               ┌───▶│ Auth Service │
                               │    └──────────────┘
┌────────┐     ┌───────────┐   │
│ Client │────▶│  Gateway  │───┼───▶┌──────────────┐
└────────┘     │  Service  │   │    │   MongoDB    │
               └───────────┘   │    │   GridFS     │
                               │    └──────────────┘
                               │
                               └───▶┌──────────────┐
                                    │   RabbitMQ   │
                                    └──────────────┘
```

- **Auth Service**: Validates tokens and handles login/register
- **MongoDB GridFS**: Stores uploaded videos and converted MP3 files
- **RabbitMQ**: Message queue for async video conversion jobs

## API Endpoints

| Method | Endpoint    | Description                        | Auth Required |
|--------|-------------|------------------------------------|---------------|
| POST   | `/login`    | Authenticate user (proxy to Auth)  | Basic Auth    |
| POST   | `/register` | Register new user (proxy to Auth)  | No            |
| POST   | `/upload`   | Upload video for conversion        | Bearer Token  |
| GET    | `/download` | Download converted MP3 file        | Bearer Token  |

## Request Flow

### Upload Flow
1. Client sends video file with Bearer token
2. Gateway validates token via Auth Service
3. Video is uploaded to MongoDB GridFS
4. Conversion job is published to RabbitMQ `video` queue
5. Client receives `video_fid` for tracking

### Download Flow
1. Client requests MP3 with `fid` query parameter
2. Gateway validates token via Auth Service
3. MP3 is streamed from MongoDB GridFS to client

## Environment Variables

| Variable            | Description                      |
|---------------------|----------------------------------|
| `AUTH_SERVICE_URL`  | Auth Service base URL            |
| `RABBITMQ_HOST`     | RabbitMQ server host             |
| `RABBITMQ_PORT`     | RabbitMQ port (default: 5672)    |
| `RABBITMQ_USER`     | RabbitMQ username                |
| `RABBITMQ_PASSWORD` | RabbitMQ password                |
| `MONGODB_URI`       | MongoDB connection string        |
| `MONGODB_DATABASE`  | MongoDB database name            |

## Running Locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8080
```

## Docker

```bash
docker build -t gateway-service .
docker run -p 8080:8080 gateway-service
```

## Usage Examples

**Register:**
```bash
curl -X POST http://localhost:8080/register \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "secret"}'
```

**Login:**
```bash
curl -X POST http://localhost:8080/login \
  -H "Authorization: Basic $(echo -n 'user@example.com:secret' | base64)"
```

**Upload Video:**
```bash
curl -X POST http://localhost:8080/upload \
  -H "Authorization: Bearer <token>" \
  -F "file=@video.mp4"
```

**Download MP3:**
```bash
curl -O http://localhost:8080/download?fid=<mp3_fid> \
  -H "Authorization: Bearer <token>"
```
