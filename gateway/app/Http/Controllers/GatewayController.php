<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\RabbitMQService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GatewayController extends Controller
{
    protected AuthService $authService;
    protected StorageService $storageService;
    protected RabbitMQService $rabbitMQService;

    public function __construct(
        AuthService $authService,
        StorageService $storageService,
        RabbitMQService $rabbitMQService
    ) {
        $this->authService = $authService;
        $this->storageService = $storageService;
        $this->rabbitMQService = $rabbitMQService;
    }

    /**
     * POST /login
     * Proxy login request to Auth Service.
     */
    public function login(Request $request): Response
    {
        $result = $this->authService->login($request);

        if ($result['error'] !== null) {
            return response($result['error'], $result['status']);
        }

        return response($result['token'], 200);
    }

    /**
     * POST /register
     * Proxy register request to Auth Service.
     */
    public function register(Request $request): JsonResponse
    {
        $result = $this->authService->register($request);

        if ($result['error'] !== null) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json([
            'message' => $result['message'],
            'token' => $result['token'],
        ], $result['status']);
    }

    /**
     * POST /upload
     * Upload a video file for MP3 conversion.
     */
    public function upload(Request $request): Response|JsonResponse
    {
        // Validate exactly one file is uploaded
        $files = $request->allFiles();
        $fileCount = 0;

        foreach ($files as $file) {
            if (is_array($file)) {
                $fileCount += count($file);
            } else {
                $fileCount++;
            }
        }

        if ($fileCount !== 1) {
            return response()->json([
                'error' => 'exactly 1 file required',
                'files_received' => array_keys($files),
                'file_count' => $fileCount,
                'content_type' => $request->header('Content-Type'),
            ], 400);
        }

        // Get the uploaded file
        $file = $request->file('file');

        if (!$file) {
            // Try to get the first file from any field
            foreach ($files as $uploadedFile) {
                if (is_array($uploadedFile)) {
                    $file = $uploadedFile[0] ?? null;
                } else {
                    $file = $uploadedFile;
                }
                break;
            }
        }

        if (!$file) {
            return response()->json('exactly 1 file required', 400);
        }

        // Upload to GridFS
        $uploadResult = $this->storageService->uploadVideo($file);

        if ($uploadResult['error'] !== null) {
            return response()->json('internal server error. Error Uploading to GridFS', 500);
        }

        $videoFid = $uploadResult['fid'];

        // Get username from token data
        $tokenData = $request->attributes->get('token_data');
        $username = $tokenData['username'] ?? $tokenData['email'] ?? 'unknown';

        // Publish message to RabbitMQ
        $publishResult = $this->rabbitMQService->publishVideoMessage($videoFid, $username);

        if (!$publishResult['success']) {
            // Rollback: delete the uploaded file
            $this->storageService->deleteVideo($videoFid);
            return response()->json('internal server error. Error deleting video', 500);
        }

        return response()->json([
            'message' => 'success! Video uploaded and queued for conversion.',
            'video_fid' => $videoFid,
        ], 200);
    }

    /**
     * GET /download
     * Download a converted MP3 file.
     */
    public function download(Request $request): Response|JsonResponse|StreamedResponse
    {
        $fid = $request->query('fid');

        if (!$fid) {
            return response()->json('fid is required', 400);
        }

        $downloadResult = $this->storageService->downloadMp3($fid);

        if ($downloadResult['error'] !== null) {
            return response()->json('internal server error', 500);
        }

        $stream = $downloadResult['stream'];
        $filename = $downloadResult['filename'];
        $length = $downloadResult['length'];

        $headers = [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        if ($length !== null) {
            $headers['Content-Length'] = $length;
        }

        return new StreamedResponse(function () use ($stream) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
                flush();
            }
            fclose($stream);
        }, 200, $headers);
    }
}
