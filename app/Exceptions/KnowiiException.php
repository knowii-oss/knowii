<?php

namespace App\Exceptions;

use Exception;

abstract class KnowiiException extends Exception
{
    protected array $errors;

    protected ?array $metadata;

    public function __construct($message = 'Knowii Exception', $code = 0, ?Exception $previous = null, array $errors = [], ?array $metadata = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
        $this->metadata = $metadata;
    }

    final public function getErrors(): array
    {
        return $this->errors;
    }

    final public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}
