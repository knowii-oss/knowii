<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

/**
 * Trait for handling API responses and ensuring API design consistency.
 */
trait ApiResponses
{
  /**
   * Newly created entity
   *
   * @param string $item
   * @return JsonResponse
   */
  final public function created(JsonResource $item): JsonResponse
  {
    return $item->response()->setStatusCode(Response::HTTP_CREATED);
  }

  /**
   * Return a success JSON response.
   *
   * @param string $message
   * @param int $statusCode
   * @return JsonResponse
   */
  final public function success(string $message = "Success", int $statusCode = Response::HTTP_OK): JsonResponse
  {
    return response()->json([
      'message' => $message,
    ], $statusCode);
  }

  /**
   * Return a success JSON response with optional message and data.
   *
   * @param string $message
   * @param array $data
   * @param int|null $statusCode
   * @return JsonResponse
   */
  final public function successWithData(string $message = "Success", array $data = [], int|null $statusCode = Response::HTTP_OK): JsonResponse
  {
    return response()->json([
      'message' => $message,
      'data' => $data,
    ], $statusCode);
  }

  /**
   * Return an error JSON response.
   *
   * @param string $errorMessage
   * @param int $statusCode
   * @return JsonResponse
   */
  final public function error(string $errorMessage, int $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
  {
    return response()->json([
      'message' => $errorMessage,
    ], $statusCode);
  }

  /**
   * Return an OK JSON response.
   *
   * @param string $message
   * @param int $statusCode
   * @return JsonResponse
   */
  final public function ok(string $message, int $statusCode = Response::HTTP_OK): JsonResponse
  {
    return $this->success($message, $statusCode);
  }
}
