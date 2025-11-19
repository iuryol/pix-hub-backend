<?php

namespace App\Exceptions\Gateway;

use App\Exceptions\GatewayException;

class GatewayAuthenticationException extends GatewayException
{
    public function __construct(
        string $gateway,
        ?array $response = null,
        ?array $request = null,
    ) {
        parent::__construct(
            message: 'Gateway authentication failed',
            gateway: $gateway,
            response: $response,
            request: $request,
        );
    }
}
