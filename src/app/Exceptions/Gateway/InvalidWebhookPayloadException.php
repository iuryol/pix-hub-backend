<?php

namespace App\Exceptions\Gateway;

use App\Exceptions\GatewayException;

class InvalidWebhookPayloadException extends GatewayException
{
    public function __construct(
        string $gateway,
        string $reason = 'Invalid webhook payload',
        ?array $payload = null,
    ) {
        parent::__construct(
            message: $reason,
            gateway: $gateway,
            response: $payload,
            request: null,
        );
    }
}
