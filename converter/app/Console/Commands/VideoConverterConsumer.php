<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use App\Services\MongoGridFSService;
use App\Services\AudioConverterService;
use App\DTO\VideoConversionMessage;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;
use Throwable;

class VideoConverterConsumer extends Command
{
    protected $signature = 'converter:consume
                            {--video-queue= : The video queue name to consume from}
                            {--mp3-queue= : The mp3 queue name to publish to}';

    protected $description = 'Consume video messages from RabbitMQ and convert them to MP3';

    private const MAX_RETRIES = 3;

    private RabbitMQService $rabbitMQ;
    private MongoGridFSService $gridFS;
    private AudioConverterService $converter;
    private string $videoQueue;
    private string $mp3Queue;

    public function handle(): int
    {
        $this->videoQueue = $this->option('video-queue') ?? config('rabbitmq.queues.video.name', 'video');
        $this->mp3Queue = $this->option('mp3-queue') ?? config('rabbitmq.queues.mp3.name', 'mp3');

        $this->info('Starting Video Converter Consumer...');
        $this->info("Consuming from queue: {$this->videoQueue}");
        $this->info("Publishing to queue: {$this->mp3Queue}");

        try {
            $this->initializeServices();
            $this->setupQueues();
            $this->startConsuming();
        } catch (Throwable $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        } finally {
            $this->cleanup();
        }

        return self::SUCCESS;
    }

    private function initializeServices(): void
    {
        $this->info('Initializing services...');

        $this->rabbitMQ = RabbitMQService::fromConfig();
        $this->gridFS = MongoGridFSService::fromConfig();
        $this->converter = AudioConverterService::fromConfig();

        $this->rabbitMQ->connect();
        $this->gridFS->connect();

        $this->info('Services initialized successfully');
    }

    private function setupQueues(): void
    {
        $this->info('Setting up queues...');

        // Declare video queue (input)
        $videoConfig = config('rabbitmq.queues.video');
        $this->rabbitMQ->declareQueue(
            $this->videoQueue,
            $videoConfig['durable'] ?? true,
            $videoConfig['auto_delete'] ?? false
        );

        // Declare mp3 queue (output)
        $mp3Config = config('rabbitmq.queues.mp3');
        $this->rabbitMQ->declareQueue(
            $this->mp3Queue,
            $mp3Config['durable'] ?? true,
            $mp3Config['auto_delete'] ?? false
        );

        // Set prefetch count
        $prefetchCount = $videoConfig['prefetch_count'] ?? 1;
        $this->rabbitMQ->setPrefetchCount($prefetchCount);

        $this->info('Queues setup completed');
    }

    private function startConsuming(): void
    {
        $this->info('Starting message consumption...');

        $this->rabbitMQ->consume($this->videoQueue, function (AMQPMessage $message) {
            $this->processMessage($message);
        });
    }

    private function processMessage(AMQPMessage $message): void
    {
        $body = $message->getBody();
        $this->info('Received message: ' . substr($body, 0, 200) . (strlen($body) > 200 ? '...' : ''));

        $uploadedMp3Id = null;

        try {
            // Parse the message
            $conversionMessage = VideoConversionMessage::fromJson($body);
            $this->info("Processing video: {$conversionMessage->videoFid} for user: {$conversionMessage->username}");

            // Download video from MongoDB
            $this->info('Downloading video from GridFS...');
            $videoContent = $this->gridFS->downloadVideo($conversionMessage->videoFid);
            $this->info('Video downloaded, size: ' . strlen($videoContent) . ' bytes');

            // Convert to MP3
            $this->info('Converting video to MP3...');
            $mp3Content = $this->converter->convertVideoToMp3($videoContent);
            $this->info('Conversion complete, MP3 size: ' . strlen($mp3Content) . ' bytes');

            // Upload MP3 to MongoDB
            $this->info('Uploading MP3 to GridFS...');
            $uploadedMp3Id = $this->gridFS->uploadMp3($mp3Content);
            $this->info("MP3 uploaded with ID: {$uploadedMp3Id}");

            // Update message with mp3_fid and publish to mp3 queue
            $updatedMessage = $conversionMessage->withMp3Fid($uploadedMp3Id);
            $this->info('Publishing completion message to mp3 queue...');
            $this->rabbitMQ->publish($this->mp3Queue, $updatedMessage->toJson());
            $this->info('Completion message published');

            // Acknowledge the original message
            $this->rabbitMQ->ack($message);
            $this->info("Successfully processed video: {$conversionMessage->videoFid}");

        } catch (Exception $e) {
            $this->error("Error processing message: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            // Cleanup uploaded MP3 if publish failed
            if ($uploadedMp3Id !== null) {
                try {
                    $this->warn("Cleaning up uploaded MP3: {$uploadedMp3Id}");
                    $this->gridFS->deleteMp3($uploadedMp3Id);
                } catch (Exception $cleanupError) {
                    $this->error("Failed to cleanup MP3: {$cleanupError->getMessage()}");
                }
            }

            // Check if this is a permanent failure (file not found or invalid ID)
            $isPermanentFailure = str_contains($e->getMessage(), 'not found')
                || str_contains($e->getMessage(), 'Invalid file ID');

            // Get retry count from message headers
            $retryCount = $this->getRetryCount($message);

            if ($isPermanentFailure) {
                // Permanent failures should not be retried
                $this->error("Permanent failure: {$e->getMessage()}. Rejecting message without requeue.");
                $this->rabbitMQ->nack($message, false);
            } elseif ($retryCount >= self::MAX_RETRIES) {
                // Max retries reached, reject without requeue
                $this->error("Max retries ({$retryCount}) reached. Rejecting message without requeue.");
                $this->rabbitMQ->nack($message, false);
            } else {
                // Negative acknowledge with requeue for retry
                $this->rabbitMQ->nack($message, true);
                $this->warn("Message returned to queue for retry (attempt " . ($retryCount + 1) . "/" . self::MAX_RETRIES . ")");
            }
        }
    }

    private function getRetryCount(AMQPMessage $message): int
    {
        $headers = $message->has('application_headers')
            ? $message->get('application_headers')->getNativeData()
            : [];

        // Check x-death header (set by RabbitMQ on requeue from DLQ)
        if (isset($headers['x-death']) && is_array($headers['x-death'])) {
            $totalCount = 0;
            foreach ($headers['x-death'] as $death) {
                $totalCount += $death['count'] ?? 0;
            }
            return $totalCount;
        }

        // Check custom retry header
        return $headers['x-retry-count'] ?? 0;
    }

    private function cleanup(): void
    {
        $this->info('Cleaning up...');

        if (isset($this->rabbitMQ)) {
            $this->rabbitMQ->close();
        }

        $this->info('Cleanup complete');
    }
}
