<?php

namespace App\Exceptions;

use Exception;

abstract class KnowiiException extends Exception
{
    /**
     * @var array<mixed>
     */
    protected array $errors;

    /**
     * @var array<mixed>|null
     */
    protected ?array $metadata;

    /**
     * @param  string  $message
     * @param  int|null  $code
     * @param  array<mixed>  $errors
     * @param  array<mixed>|null  $metadata
     */
    public function __construct($message = 'Knowii Exception', $code = 0, ?Exception $previous = null, array $errors = [], ?array $metadata = null)
    {
        parent::__construct($message, $code ?? 0, $previous);
        $this->errors = $errors;
        $this->metadata = $metadata;
    }

    /**
     * @return array<mixed>
     */
    final public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<mixed>|null
     */
    final public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}
