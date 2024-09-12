<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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
    protected function success(string $message = "Success", int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message, int $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Return an OK JSON response.
     *
     * @param  string message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function ok(string $message, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return $this->success($message, $statusCode);
    }
}
