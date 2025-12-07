# Converter Service

Video-to-MP3 conversion microservice that processes videos asynchronously via message queue.

## Overview

The Converter Service consumes video conversion jobs from RabbitMQ, downloads videos from MongoDB GridFS, converts them to MP3 using FFmpeg, and uploads the result back to GridFS. It operates as a background worker with no HTTP endpoints.

## System Integration

```
┌──────────────┐     ┌───────────────┐     ┌───────────────────┐
│   RabbitMQ   │────▶│   Converter   │────▶│   RabbitMQ        │
│ (video queue)│     │    Service    │     │   (mp3 queue)     │
└──────────────┘     └───────────────┘     └───────────────────┘
                            │
                   ┌────────┴────────┐
                   ▼                 ▼
            ┌──────────────┐  ┌──────────────┐
            │   MongoDB    │  │    FFmpeg    │
            │   GridFS     │  │              │
            └──────────────┘  └──────────────┘
```

- **RabbitMQ (video queue)**: Receives conversion job messages from Gateway
- **MongoDB GridFS**: Stores video files (input) and MP3 files (output)
- **FFmpeg**: Handles the actual video-to-audio conversion
- **RabbitMQ (mp3 queue)**: Publishes completion notifications

## Message Flow

1. Gateway uploads video to GridFS and publishes message to `video` queue
2. Converter consumes message containing `video_fid` and `username`
3. Downloads video from GridFS using `video_fid`
4. Converts video to MP3 (192kbps, 44100Hz) using FFmpeg
5. Uploads MP3 to GridFS
6. Publishes completion message with `mp3_fid` to `mp3` queue

## Message Format

**Input (video queue):**
```json
{
  "video_fid": "507f1f77bcf86cd799439011",
  "username": "user@example.com"
}
```

**Output (mp3 queue):**
```json
{
  "video_fid": "507f1f77bcf86cd799439011",
  "mp3_fid": "507f1f77bcf86cd799439022",
  "username": "user@example.com"
}
```

## Environment Variables

| Variable              | Description                      |
|-----------------------|----------------------------------|
| `RABBITMQ_HOST`       | RabbitMQ server host             |
| `RABBITMQ_PORT`       | RabbitMQ port (default: 5672)    |
| `RABBITMQ_USER`       | RabbitMQ username                |
| `RABBITMQ_PASSWORD`   | RabbitMQ password                |
| `MONGODB_URI`         | MongoDB connection string        |
| `MONGODB_DATABASE`    | MongoDB database name            |
| `FFMPEG_PATH`         | Path to FFmpeg binary            |

## Running the Consumer

```bash
composer install
cp .env.example .env
php artisan converter:consume
```

With custom queue names:
```bash
php artisan converter:consume --video-queue=video --mp3-queue=mp3
```

## Docker

```bash
docker build -t converter-service .
docker run converter-service
```

## Error Handling

- Failed conversions are retried up to 3 times
- Permanent failures (file not found, invalid ID) are not retried
- Uploaded MP3 files are cleaned up if publishing fails
