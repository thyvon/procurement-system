<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(mixed $data = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        $body = ['data' => $data];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function error(int $statusCode, string $message, string $error): JsonResponse
    {
        return response()->json([
            'statusCode' => $statusCode,
            'message' => $message,
            'error' => $error,
            'correlationId' => app()->bound('correlation_id') ? app('correlation_id') : null,
        ], $statusCode);
    }
}
