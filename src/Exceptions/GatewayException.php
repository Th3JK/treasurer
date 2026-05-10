<?php

namespace Th3JK\Treasurer\Exceptions;

use RuntimeException;
use Throwable;

class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $statusCode = null,
        public readonly ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
