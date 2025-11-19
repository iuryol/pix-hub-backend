<?php

namespace App\Exceptions\Gateway;

use App\Exceptions\GatewayException;

class GatewayValidationException extends GatewayException
{
    public function __construct(
        string $gateway,
        public readonly array $errors = [],
        ?array $response = null,
        ?array $request = null,
    ) {
        parent::__construct(
            message: 'Gateway validation failed',
            gateway: $gateway,
            response: $response,
            request: $request,
        );
    }
}
