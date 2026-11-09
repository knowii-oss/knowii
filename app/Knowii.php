<?php

namespace App;

use TaylorNetwork\UsernameGenerator\Generator;

class Knowii
{
    /**
     * Generate a random username based on the user's name
     * References:
     * https://github.com/taylornetwork/laravel-username-generator
     * https://docs.taylornetwork.io/laravel-username-generator/
     *
     * @param  string  $name  the user's name
     */
    public static function generateRandomUsername(string $name): string
    {
        $generator = new Generator;
        $retVal = $generator->generate($name);

        return $retVal;
    }
}
