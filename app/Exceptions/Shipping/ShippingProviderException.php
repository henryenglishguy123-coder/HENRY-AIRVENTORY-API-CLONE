<?php

namespace App\Exceptions\Shipping;

use Exception;
use Throwable;

class ShippingProviderException extends Exception
{
    protected ?array $payload;

    public function __construct(string $message = '', int $code = 0, ?array $payload = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->payload = $payload;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }
}
