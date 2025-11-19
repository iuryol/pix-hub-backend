<?php

namespace App\Exceptions\Gateway;

use App\Exceptions\GatewayException;

class GatewayRateLimitException extends GatewayException
{
    public function __construct(
        string $gateway,
        public readonly ?int $retryAfter = null,
        ?array $response = null,
        ?array $request = null,
    ) {
        parent::__construct(
            message: 'Gateway rate limit exceeded',
            gateway: $gateway,
            response: $response,
            request: $request,
        );
    }
}
