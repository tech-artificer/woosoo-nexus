<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success($data = [], string $message = 'OK', int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($payload, $status);
    }

    public static function error(string $message = 'Error', $errors = null, int $status = 500): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function notFound(string $message = 'Not Found', $meta = [], int $status = 404): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
