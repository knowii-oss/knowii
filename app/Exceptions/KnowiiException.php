<?php

namespace App\Exceptions;

use Exception;

abstract class KnowiiException extends Exception
{
    /**
     * Messages per field, like the errors of a validation
     *
     * @var array<string, array<int, string>>
     */
    protected array $errors;

    /**
     * @var array<string, bool|int|string|array<string, int|null>|null>|null
     */
    protected ?array $metadata;

    /**
     * @param  string  $message
     * @param  int|null  $code
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, bool|int|string|array<string, int|null>|null>|null  $metadata
     */
    public function __construct($message = 'Knowii Exception', $code = 0, ?Exception $previous = null, array $errors = [], ?array $metadata = null)
    {
        parent::__construct($message, $code ?? 0, $previous);
        $this->errors = $errors;
        $this->metadata = $metadata;
    }

    /**
     * @return array<string, array<int, string>>
     */
    final public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, bool|int|string|array<string, int|null>|null>|null
     */
    final public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}
