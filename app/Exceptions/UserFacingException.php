<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Expected business error with a plain-language message for practice staff.
 */
class UserFacingException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function httpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
