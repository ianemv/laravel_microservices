<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitMQService
{
    protected ?AMQPStreamConnection $connection = null;
    protected ?AMQPChannel $channel = null;

    /**
     * Get or create a connection to RabbitMQ.
     */
    protected function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                config('services.rabbitmq.host'),
                config('services.rabbitmq.port'),
                config('services.rabbitmq.user'),
                config('services.rabbitmq.password')
            );
        }

        return $this->connection;
    }

    /**
     * Get or create a channel.
     */
    protected function getChannel(): AMQPChannel
    {
        if ($this->channel === null || !$this->channel->is_open()) {
            $this->channel = $this->getConnection()->channel();
        }

        return $this->channel;
    }

    /**
     * Publish a message to the video queue.
     *
     * @return array{success: bool, error: string|null}
     */
    public function publishVideoMessage(string $videoFid, string $username): array
    {
        try {
            $channel = $this->getChannel();

            // Declare the queue (durable)
            $channel->queue_declare(
                'video',    // queue name
                false,      // passive
                true,       // durable
                false,      // exclusive
                false       // auto_delete
            );

            // Create the message
            $messageBody = json_encode([
                'video_fid' => $videoFid,
                'mp3_fid' => null,
                'username' => $username,
            ]);

            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            // Publish the message
            $channel->basic_publish($message, '', 'video');

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Close the connection.
     */
    public function close(): void
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            $this->channel->close();
        }

        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    /**
     * Destructor to ensure connection is closed.
     */
    public function __destruct()
    {
        $this->close();
    }
}
