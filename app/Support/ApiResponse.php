<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Single source of truth for the API response envelope.
 *
 * Success:
 *   { "success": true, "message": "...", "data": {}, "meta": null }
 *
 * Error:
 *   { "success": false, "message": "...", "error": { "code": "...", "details": {} } }
 */
final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Operation completed successfully',
        ?array $meta = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
            'meta' => $meta,
        ], $status);
    }

    public static function error(
        string $code,
        string $message,
        int $status,
        ?array $details = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => $code,
                'details' => $details ?? (object) [],
            ],
        ], $status);
    }
}
