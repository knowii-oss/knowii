<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait for handling API responses and ensuring API design consistency.
 */
trait ApiResponses
{
    /**
     * Return a success JSON response.
     *
     * @param  array|string  $data
     * @param  string  $message
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success(string $message = "Success", int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'status' => $statusCode,
        ], $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'status' => $statusCode,
        ], $statusCode);
    }

    /**
     * Return an OK JSON response.
     *
     * @param  string message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function ok(string $message, int $statusCode = 200): JsonResponse
    {
        return $this->success($message, $statusCode);
    }
}
