<?php

namespace App\Exceptions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class KnowiiValidationException extends KnowiiException
{
  protected $validator;

  public function __construct(Validator $validator)
  {
    parent::__construct();
    $this->validator = $validator;
  }

  final public function render(): JsonResponse
  {
    return response()->json([
      "error" => "form validation error",
      "message" => $this->validator->errors()->first(),
    ], Response::HTTP_UNPROCESSABLE_ENTITY);
  }
}
