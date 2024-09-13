<?php

namespace App\OpenApi\Requests;

use App\OpenApi\Schemas\LoginApiRequestBodySchema;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\RequestBody;
use Vyuldashev\LaravelOpenApi\Factories\RequestBodyFactory;

class LoginApiRequestBody extends RequestBodyFactory {
  public function build(): RequestBody {
    return RequestBody::create('Login')
      ->description('Log in data')
      ->content(
        MediaType::json()->schema(LoginApiRequestBodySchema::ref())
      );
  }
}
