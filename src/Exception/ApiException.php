<?php

declare(strict_types=1);

namespace CoinoLive\Exception;

/** The API refused the request; getMessage() says why. */
class ApiException extends \RuntimeException
{
    /** @param array<string, list<string>> $errors Field errors, when the API names fields. */
    public function __construct(string $message, public readonly int $status = 0, public readonly array $errors = [])
    {
        parent::__construct($message, $status);
    }
}
