<?php

namespace App\Exceptions;

use Exception;

class UnsupportedGatewayException extends Exception
{
    public function __construct(string $gateway)
    {
        parent::__construct("Gateway '{$gateway}' is not supported.");
    }
}
