<?php

namespace App\Services;

use MongoDB\Client;
use MongoDB\GridFS\Bucket;
use MongoDB\BSON\ObjectId;
use Illuminate\Http\UploadedFile;

class StorageService
{
    protected Client $client;
    protected string $databaseName;

    public function __construct()
    {
        $this->client = new Client(config('database.connections.mongodb.dsn'));
        $this->databaseName = config('database.connections.mongodb.database');
    }

    /**
     * Get GridFS bucket for videos.
     */
    protected function getVideosBucket(): Bucket
    {
        return $this->client->selectDatabase($this->databaseName)->selectGridFSBucket([
            'bucketName' => 'videos',
        ]);
    }

    /**
     * Get GridFS bucket for MP3s.
     */
    protected function getMp3sBucket(): Bucket
    {
        return $this->client->selectDatabase($this->databaseName)->selectGridFSBucket([
            'bucketName' => 'mp3s',
        ]);
    }

    /**
     * Upload a video file to GridFS.
     *
     * @return array{fid: string|null, error: string|null}
     */
    public function uploadVideo(UploadedFile $file): array
    {
        try {
            $bucket = $this->getVideosBucket();
            $stream = fopen($file->getRealPath(), 'rb');

            if ($stream === false) {
                return [
                    'fid' => null,
                    'error' => 'Failed to read uploaded file',
                ];
            }

            $fileId = $bucket->uploadFromStream(
                $file->getClientOriginalName(),
                $stream
            );

            fclose($stream);

            return [
                'fid' => (string) $fileId,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'fid' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a video file from GridFS.
     */
    public function deleteVideo(string $fileId): bool
    {
        try {
            $bucket = $this->getVideosBucket();
            $bucket->delete(new ObjectId($fileId));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Download an MP3 file from GridFS.
     *
     * @return array{stream: resource|null, filename: string|null, length: int|null, error: string|null}
     */
    public function downloadMp3(string $fileId): array
    {
        try {
            $bucket = $this->getMp3sBucket();
            $objectId = new ObjectId($fileId);

            // Find the file to get metadata
            $file = $bucket->findOne(['_id' => $objectId]);

            if (!$file) {
                return [
                    'stream' => null,
                    'filename' => null,
                    'length' => null,
                    'error' => 'File not found',
                ];
            }

            $stream = $bucket->openDownloadStream($objectId);

            return [
                'stream' => $stream,
                'filename' => $file->filename ?? "{$fileId}.mp3",
                'length' => $file->length ?? null,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'stream' => null,
                'filename' => null,
                'length' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
