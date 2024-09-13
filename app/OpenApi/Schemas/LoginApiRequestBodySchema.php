<?php

namespace App\OpenApi\Schemas;

use GoldSpecDigital\ObjectOrientedOAS\Contracts\SchemaContract;
use GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Vyuldashev\LaravelOpenApi\Contracts\Reusable;
use Vyuldashev\LaravelOpenApi\Factories\SchemaFactory;

class LoginApiRequestBodySchema extends SchemaFactory implements Reusable
{
  /**
   * @return SchemaContract Schema
   *
   * @throws InvalidArgumentException
   */
  public function build(): SchemaContract
  {
    return Schema::object('Login')
      ->required('email', 'password')
      ->properties(
        Schema::string('email')->format(Schema::TYPE_STRING)->default(''),
        Schema::string('password')->format(Schema::FORMAT_PASSWORD)->default(''),
        Schema::boolean('includeToken')->default(false),
      );
  }
}
