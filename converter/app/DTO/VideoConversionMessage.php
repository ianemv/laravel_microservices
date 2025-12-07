<?php

namespace App\DTO;

use InvalidArgumentException;

class VideoConversionMessage
{
    public function __construct(
        public readonly string $videoFid,
        public readonly ?string $mp3Fid,
        public readonly string $username,
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['video_fid'])) {
            throw new InvalidArgumentException('Missing required field: video_fid');
        }

        if (!isset($data['username'])) {
            throw new InvalidArgumentException('Missing required field: username');
        }

        return new self(
            videoFid: $data['video_fid'],
            mp3Fid: $data['mp3_fid'] ?? null,
            username: $data['username'],
        );
    }

    public function toArray(): array
    {
        return [
            'video_fid' => $this->videoFid,
            'mp3_fid' => $this->mp3Fid,
            'username' => $this->username,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function withMp3Fid(string $mp3Fid): self
    {
        return new self(
            videoFid: $this->videoFid,
            mp3Fid: $mp3Fid,
            username: $this->username,
        );
    }
}
