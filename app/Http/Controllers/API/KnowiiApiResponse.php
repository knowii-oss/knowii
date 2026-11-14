<?php

namespace App\Http\Controllers\API;

use App\Enums\KnowiiApiResponseCategory;
use App\Enums\KnowiiApiResponseType;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class KnowiiApiResponse implements JsonSerializable
{
    private KnowiiApiResponseCategory $category;

    private KnowiiApiResponseType $type;

    private string $message;

    /**
     * Pagination and flags about the answer
     *
     * @var array<string, bool|int|string|array<string, int|null>|null>|null
     */
    private ?array $metadata;

    /**
     * @var array<mixed>|JsonResource|null
     */
    private array|JsonResource|null $data;

    /**
     * Messages per field
     *
     * @var array<string, array<int, string>>|null
     */
    private ?array $errors;

    /**
     * @param  array<string, bool|int|string|array<string, int|null>|null>|null  $metadata
     * @param  array<mixed>|JsonResource|null  $data  the JSON payload of the endpoint
     * @param  array<string, array<int, string>>|null  $errors
     */
    public function __construct(
        KnowiiApiResponseCategory $category,
        KnowiiApiResponseType $type,
        string $message,
        ?array $metadata = null,
        array|JsonResource|null $data = null,
        ?array $errors = null
    ) {
        $this->category = $category;
        $this->type = $type;
        $this->message = $message;
        $this->metadata = $metadata;
        $this->data = $data;
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    final public function jsonSerialize(): array
    {
        $retVal = [
            'category' => $this->category->value,
            'type' => $this->type->value,
            'message' => $this->message,
        ];

        if (! is_null($this->metadata)) {
            if (! empty($this->metadata)) {
                $retVal['metadata'] = $this->metadata;
            } else {
                // We always want a JSON object, even if it's empty.
                $retVal['metadata'] = new \stdClass;
            }
        }

        if (! is_null($this->data)) {
            if (! empty($this->data)) {
                $retVal['data'] = $this->data;
            } else {
                // We always want a JSON object, even if it's empty.
                $retVal['data'] = new \stdClass;
            }
        }

        if (! is_null($this->errors)) {
            if (! empty($this->errors)) {
                $retVal['errors'] = $this->errors;
            } else {
                // We always want a JSON object, even if it's empty.
                $retVal['errors'] = new \stdClass;
            }
        }

        return $retVal;
    }
}
