<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when import is attempted before required business configuration exists (ADR-031).
 */
class BusinessConfigurationIncompleteException extends RuntimeException
{
}
