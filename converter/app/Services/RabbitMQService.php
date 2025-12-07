<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;
use Exception;

class RabbitMQService
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $vhost,
        private readonly int $maxRetries,
        private readonly int $retryDelay,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            host: config('rabbitmq.host'),
            port: config('rabbitmq.port'),
            user: config('rabbitmq.user'),
            password: config('rabbitmq.password'),
            vhost: config('rabbitmq.vhost'),
            maxRetries: config('rabbitmq.retry.max_attempts'),
            retryDelay: config('rabbitmq.retry.delay_seconds'),
        );
    }

    public function connect(): void
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;
                Log::info("Attempting to connect to RabbitMQ (attempt {$attempt}/{$this->maxRetries})");

                $this->connection = new AMQPStreamConnection(
                    $this->host,
                    $this->port,
                    $this->user,
                    $this->password,
                    $this->vhost
                );

                $this->channel = $this->connection->channel();
                Log::info('Successfully connected to RabbitMQ');

                return;
            } catch (Exception $e) {
                Log::warning("Failed to connect to RabbitMQ: {$e->getMessage()}");

                if ($attempt >= $this->maxRetries) {
                    Log::error('Max retries exceeded. Giving up connection to RabbitMQ.');
                    throw $e;
                }

                Log::info("Retrying in {$this->retryDelay} seconds...");
                sleep($this->retryDelay);
            }
        }
    }

    public function declareQueue(string $queueName, bool $durable = true, bool $autoDelete = false): void
    {
        $this->ensureConnected();

        $this->channel->queue_declare(
            $queueName,
            false,      // passive
            $durable,   // durable
            false,      // exclusive
            $autoDelete // auto_delete
        );

        Log::info("Declared queue: {$queueName}");
    }

    public function setPrefetchCount(int $count): void
    {
        $this->ensureConnected();

        $this->channel->basic_qos(
            null,   // prefetch_size
            $count, // prefetch_count
            null    // global
        );

        Log::info("Set prefetch count to: {$count}");
    }

    public function consume(string $queueName, callable $callback): void
    {
        $this->ensureConnected();

        $this->channel->basic_consume(
            $queueName,
            '',     // consumer_tag
            false,  // no_local
            false,  // no_ack (we want manual ack)
            false,  // exclusive
            false,  // nowait
            $callback
        );

        Log::info("Started consuming from queue: {$queueName}");

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function publish(string $queueName, string $message): void
    {
        $this->ensureConnected();

        $msg = new AMQPMessage($message, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish($msg, '', $queueName);

        Log::info("Published message to queue: {$queueName}");
    }

    public function ack(AMQPMessage $message): void
    {
        $message->ack();
        Log::debug('Message acknowledged');
    }

    public function nack(AMQPMessage $message, bool $requeue = true): void
    {
        $message->nack($requeue);
        Log::debug('Message negative acknowledged, requeue: ' . ($requeue ? 'yes' : 'no'));
    }

    public function close(): void
    {
        if ($this->channel !== null) {
            try {
                $this->channel->close();
            } catch (Exception $e) {
                Log::warning("Error closing channel: {$e->getMessage()}");
            }
            $this->channel = null;
        }

        if ($this->connection !== null) {
            try {
                $this->connection->close();
            } catch (Exception $e) {
                Log::warning("Error closing connection: {$e->getMessage()}");
            }
            $this->connection = null;
        }

        Log::info('RabbitMQ connection closed');
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    private function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
