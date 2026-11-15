<?php

namespace App\Support;

/**
 * What the provider said about one subject (#1386, ADR 0047): the active keys, or that it knows nothing about the subject
 * any more (`gone`: the link at the provider was removed, or the account was deleted there). Only keys, nothing else.
 */
final readonly class SubjectEntitlements
{
    /**
     * @param  list<string>  $keys  Valid, unique and sorted
     */
    private function __construct(public bool $gone, public array $keys) {}

    /**
     * @param  list<string>  $keys
     */
    public static function found(array $keys): self
    {
        return new self(false, $keys);
    }

    public static function gone(): self
    {
        return new self(true, []);
    }
}
