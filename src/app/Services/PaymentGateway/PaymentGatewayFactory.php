<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGateway\PaymentGatewayInterface;
use App\Exceptions\UnsupportedGatewayException;
use App\Models\Subacquirer;
use App\Services\PaymentGateway\Gateways\SubadqAGateway;
use App\Services\PaymentGateway\Gateways\SubadqBGateway;

class PaymentGatewayFactory
{
    /**
     * Create a gateway instance based on the subacquirer
     *
     * @throws UnsupportedGatewayException
     */
    public function make(Subacquirer $subacquirer): PaymentGatewayInterface
    {
        return match ($subacquirer->slug) {
            'subadq-a' => new SubadqAGateway($subacquirer),
            'subadq-b' => new SubadqBGateway($subacquirer),
            'mock' => new MockGateway($subacquirer),
            default => throw new UnsupportedGatewayException($subacquirer->slug),
        };
    }

    /**
     * Create a gateway instance by slug
     *
     * @throws UnsupportedGatewayException
     */
    public function makeBySlug(string $slug): PaymentGatewayInterface
    {
        $subacquirer = Subacquirer::where('slug', $slug)->first();

        if (!$subacquirer) {
            throw new UnsupportedGatewayException($slug);
        }

        return $this->make($subacquirer);
    }
}
