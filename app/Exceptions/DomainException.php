<?php

namespace App\Exceptions;

use Exception;

/**
 * Base class for expected, business-rule failures raised by a service.
 *
 * Services throw this instead of reaching for `abort()` (which couples them to
 * HTTP) or `ValidationException` (which reports a rule failure that did not
 * happen). The handler in bootstrap/app.php renders it through the project's
 * error envelope using the status carried here.
 */
class DomainException extends Exception
{
    /**
     * @param  array<string, list<string>>|null  $errors  field-keyed detail, when the failure maps onto input
     */
    public function __construct(
        string $message,
        private readonly int $status = 400,
        private readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    /**
     * The HTTP status this failure should be reported with.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function errors(): ?array
    {
        return $this->errors;
    }
}
