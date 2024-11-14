<?php

namespace App\OpenApi\Parameters;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Vyuldashev\LaravelOpenApi\Factories\ParametersFactory;

class LoginApiRequestParameters extends ParametersFactory
{
    /**
     * @return Parameter[]
     */
    public function build(): array
    {
        return [
            Parameter::header()
                ->name('Accept')
                ->description('MUST request a JSON response')
                ->required(true)
                ->schema(Schema::string()->default('application/json')),
        ];
    }
}
