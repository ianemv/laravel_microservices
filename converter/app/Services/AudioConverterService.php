<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Exception;

class AudioConverterService
{
    private string $tempDirectory;
    private string $ffmpegPath;
    private string $ffprobePath;

    public function __construct(
        ?string $tempDirectory = null,
        ?string $ffmpegPath = null,
        ?string $ffprobePath = null,
    ) {
        $this->tempDirectory = $tempDirectory ?? config('services.converter.temp_directory', sys_get_temp_dir());
        $this->ffmpegPath = $ffmpegPath ?? config('services.converter.ffmpeg_path', 'ffmpeg');
        $this->ffprobePath = $ffprobePath ?? config('services.converter.ffprobe_path', 'ffprobe');
    }

    public static function fromConfig(): self
    {
        return new self(
            config('services.converter.temp_directory'),
            config('services.converter.ffmpeg_path'),
            config('services.converter.ffprobe_path'),
        );
    }

    public function convertVideoToMp3(string $videoContent): string
    {
        $videoPath = $this->createTempFile($videoContent, '.mp4');
        $mp3Path = $this->generateTempPath('.mp3');

        try {
            // Check if video has an audio stream
            if (!$this->hasAudioStream($videoPath)) {
                throw new Exception('Video file has no audio track');
            }

            // Convert to MP3
            $this->executeConversion($videoPath, $mp3Path);

            // Read the MP3 content
            $mp3Content = file_get_contents($mp3Path);

            if ($mp3Content === false) {
                throw new Exception('Failed to read converted MP3 file');
            }

            Log::info('Successfully converted video to MP3, size: ' . strlen($mp3Content) . ' bytes');

            return $mp3Content;
        } finally {
            // Cleanup temporary files
            $this->cleanupTempFile($videoPath);
            $this->cleanupTempFile($mp3Path);
        }
    }

    private function hasAudioStream(string $videoPath): bool
    {
        $process = new Process([
            $this->ffprobePath,
            '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_type',
            '-of', 'csv=p=0',
            $videoPath,
        ]);

        $process->run();

        $output = trim($process->getOutput());

        Log::debug("FFprobe audio check output: '{$output}'");

        return $output === 'audio';
    }

    private function executeConversion(string $videoPath, string $mp3Path): void
    {
        Log::info("Converting video to MP3: {$videoPath} -> {$mp3Path}");

        $process = new Process([
            $this->ffmpegPath,
            '-i', $videoPath,
            '-vn',                    // No video
            '-acodec', 'libmp3lame',  // MP3 codec
            '-ab', '192k',            // Bitrate
            '-ar', '44100',           // Sample rate
            '-y',                     // Overwrite output
            $mp3Path,
        ]);

        $process->setTimeout(600); // 10 minutes timeout for large files
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error("FFmpeg conversion failed: {$process->getErrorOutput()}");
            throw new ProcessFailedException($process);
        }

        Log::info('FFmpeg conversion completed successfully');
    }

    private function createTempFile(string $content, string $extension): string
    {
        $path = $this->generateTempPath($extension);

        if (file_put_contents($path, $content) === false) {
            throw new Exception("Failed to write temporary file: {$path}");
        }

        Log::debug("Created temporary file: {$path}, size: " . strlen($content) . ' bytes');

        return $path;
    }

    private function generateTempPath(string $extension): string
    {
        return $this->tempDirectory . DIRECTORY_SEPARATOR . uniqid('converter_', true) . $extension;
    }

    private function cleanupTempFile(string $path): void
    {
        if (file_exists($path)) {
            if (unlink($path)) {
                Log::debug("Cleaned up temporary file: {$path}");
            } else {
                Log::warning("Failed to cleanup temporary file: {$path}");
            }
        }
    }
}
