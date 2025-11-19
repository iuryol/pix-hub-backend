<?php

namespace App\Exceptions\Gateway;

use App\Exceptions\GatewayException;

class GatewayConnectionException extends GatewayException
{
    public function __construct(
        string $gateway,
        string $message = 'Failed to connect to gateway',
        ?array $request = null,
    ) {
        parent::__construct(
            message: $message,
            gateway: $gateway,
            response: null,
            request: $request,
        );
    }
}
