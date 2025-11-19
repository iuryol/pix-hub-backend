<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class GatewayException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $gateway,
        public readonly ?array $response = null,
        public readonly ?array $request = null,
    ) {
        parent::__construct($message);
    }

    public function report(): void
    {
        Log::channel('gateway')->error($this->message, [
            'gateway' => $this->gateway,
            'request' => $this->request,
            'response' => $this->response,
        ]);
    }

    public function context(): array
    {
        return [
            'gateway' => $this->gateway,
            'response' => $this->response,
        ];
    }
}
