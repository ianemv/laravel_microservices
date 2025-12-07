<?php

namespace App\Services;

use MongoDB\Client;
use MongoDB\GridFS\Bucket;
use MongoDB\BSON\ObjectId;
use Illuminate\Support\Facades\Log;
use Exception;

class MongoGridFSService
{
    private ?Client $client = null;
    private array $buckets = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $authDatabase,
        private readonly array $databases,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            host: config('mongodb.host'),
            port: config('mongodb.port'),
            username: config('mongodb.username'),
            password: config('mongodb.password'),
            authDatabase: config('mongodb.auth_database'),
            databases: config('mongodb.databases'),
        );
    }

    public function connect(): void
    {
        if ($this->client !== null) {
            return;
        }

        $uri = $this->buildConnectionUri();

        try {
            Log::info("Connecting to MongoDB at {$this->host}:{$this->port}");
            $this->client = new Client($uri);
            Log::info('Successfully connected to MongoDB');
        } catch (Exception $e) {
            Log::error("Failed to connect to MongoDB: {$e->getMessage()}");
            throw $e;
        }
    }

    private function buildConnectionUri(): string
    {
        $uri = 'mongodb://';

        if ($this->username && $this->password) {
            $uri .= urlencode($this->username) . ':' . urlencode($this->password) . '@';
        }

        $uri .= "{$this->host}:{$this->port}";

        if ($this->username && $this->password) {
            $uri .= "/?authSource={$this->authDatabase}";
        }

        return $uri;
    }

    private function getBucket(string $type): Bucket
    {
        if (!isset($this->buckets[$type])) {
            $this->connect();

            if (!isset($this->databases[$type])) {
                throw new Exception("Unknown database type: {$type}");
            }

            $dbConfig = $this->databases[$type];
            $database = $this->client->selectDatabase($dbConfig['name']);
            $this->buckets[$type] = $database->selectGridFSBucket([
                'bucketName' => $dbConfig['bucket'],
            ]);

            Log::info("Initialized GridFS bucket: {$type}");
        }

        return $this->buckets[$type];
    }

    public function downloadVideo(string $fileId): string
    {
        $bucket = $this->getBucket('videos');

        // Validate ObjectId format (24 hex characters)
        if (!preg_match('/^[a-f0-9]{24}$/i', $fileId)) {
            Log::error("Invalid ObjectId format: {$fileId}");
            throw new Exception("Invalid file ID format: {$fileId}");
        }

        $objectId = new ObjectId($fileId);

        Log::info("Downloading video file: {$fileId}");

        if (!$this->videoExists($fileId)) {
            Log::error("Video file not found in GridFS: {$fileId}");
            throw new Exception("Video file not found: {$fileId}");
        }

        $stream = $bucket->openDownloadStream($objectId);
        $content = stream_get_contents($stream);
        fclose($stream);

        Log::info("Downloaded video file: {$fileId}, size: " . strlen($content) . ' bytes');

        return $content;
    }

    public function uploadMp3(string $content, ?string $filename = null): string
    {
        $bucket = $this->getBucket('mp3s');
        $filename = $filename ?? uniqid('audio_') . '.mp3';

        Log::info("Uploading MP3 file: {$filename}");

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $objectId = $bucket->uploadFromStream($filename, $stream);
        fclose($stream);

        $fileId = (string) $objectId;
        Log::info("Uploaded MP3 file with ID: {$fileId}");

        return $fileId;
    }

    public function deleteMp3(string $fileId): void
    {
        $bucket = $this->getBucket('mp3s');
        $objectId = new ObjectId($fileId);

        Log::info("Deleting MP3 file: {$fileId}");

        $bucket->delete($objectId);

        Log::info("Deleted MP3 file: {$fileId}");
    }

    public function videoExists(string $fileId): bool
    {
        try {
            $bucket = $this->getBucket('videos');
            $objectId = new ObjectId($fileId);

            $file = $bucket->findOne(['_id' => $objectId]);

            return $file !== null;
        } catch (Exception $e) {
            Log::warning("Error checking if video exists: {$e->getMessage()}");
            return false;
        }
    }

    public function mp3Exists(string $fileId): bool
    {
        try {
            $bucket = $this->getBucket('mp3s');
            $objectId = new ObjectId($fileId);

            $file = $bucket->findOne(['_id' => $objectId]);

            return $file !== null;
        } catch (Exception $e) {
            Log::warning("Error checking if MP3 exists: {$e->getMessage()}");
            return false;
        }
    }
}
