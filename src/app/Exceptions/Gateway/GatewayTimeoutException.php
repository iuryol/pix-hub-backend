<?php

namespace App\Exceptions\Gateway;

use App\Exceptions\GatewayException;

class GatewayTimeoutException extends GatewayException
{
    public function __construct(
        string $gateway,
        int $timeout = 30,
        ?array $request = null,
    ) {
        parent::__construct(
            message: "Gateway request timed out after {$timeout} seconds",
            gateway: $gateway,
            response: null,
            request: $request,
        );
    }
}
