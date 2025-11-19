<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Contracts\PaymentGateway\PaymentGatewayInterface;
use App\Exceptions\Gateway\GatewayAuthenticationException;
use App\Exceptions\Gateway\GatewayConnectionException;
use App\Exceptions\Gateway\GatewayRateLimitException;
use App\Exceptions\Gateway\GatewayTimeoutException;
use App\Exceptions\Gateway\GatewayValidationException;
use App\Exceptions\GatewayException;
use App\Models\Subacquirer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected array $credentials;
    protected int $timeout = 30;

    public function __construct(
        protected Subacquirer $subacquirer
    ) {
        $this->baseUrl = rtrim($subacquirer->base_url, '/');
        $this->credentials = $subacquirer->credentials ?? [];
    }

    public function getIdentifier(): string
    {
        return $this->subacquirer->slug;
    }

    /**
     * Make HTTP request to the gateway
     *
     * @throws GatewayException
     * @throws GatewayConnectionException
     * @throws GatewayTimeoutException
     * @throws GatewayAuthenticationException
     * @throws GatewayRateLimitException
     * @throws GatewayValidationException
     */
    protected function request(
        string $method,
        string $endpoint,
        array $data = [],
        array $headers = []
    ): array {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $defaultHeaders = $this->getDefaultHeaders();
        $allHeaders = array_merge($defaultHeaders, $headers);

        Log::channel('gateway')->info("Gateway request: {$method} {$url}", [
            'gateway' => $this->getIdentifier(),
            'data' => $data,
        ]);

        try {
            $response = Http::withHeaders($allHeaders)
                ->timeout($this->timeout)
                ->{strtolower($method)}($url, $data);

            $responseData = $response->json() ?? [];

            Log::channel('gateway')->info("Gateway response", [
                'gateway' => $this->getIdentifier(),
                'status' => $response->status(),
                'data' => $responseData,
            ]);

            if ($response->failed()) {
                $this->handleFailedResponse($response->status(), $responseData, $data);
            }

            return $responseData;

        } catch (GatewayException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out')) {
                throw new GatewayTimeoutException(
                    gateway: $this->getIdentifier(),
                    timeout: $this->timeout,
                    request: $data,
                );
            }

            throw new GatewayConnectionException(
                gateway: $this->getIdentifier(),
                message: $e->getMessage(),
                request: $data,
            );
        } catch (\Exception $e) {
            throw new GatewayException(
                message: $e->getMessage(),
                gateway: $this->getIdentifier(),
                response: null,
                request: $data,
            );
        }
    }

    /**
     * Handle failed HTTP response with specific exceptions
     *
     * @throws GatewayException
     */
    protected function handleFailedResponse(int $status, array $responseData, array $requestData): void
    {
        $message = $responseData['message'] ?? "Gateway request failed with status {$status}";

        match ($status) {
            401 => throw new GatewayAuthenticationException(
                gateway: $this->getIdentifier(),
                response: $responseData,
                request: $requestData,
            ),
            422 => throw new GatewayValidationException(
                gateway: $this->getIdentifier(),
                errors: $responseData['errors'] ?? [],
                response: $responseData,
                request: $requestData,
            ),
            429 => throw new GatewayRateLimitException(
                gateway: $this->getIdentifier(),
                retryAfter: $responseData['retry_after'] ?? null,
                response: $responseData,
                request: $requestData,
            ),
            default => throw new GatewayException(
                message: $message,
                gateway: $this->getIdentifier(),
                response: $responseData,
                request: $requestData,
            ),
        };
    }

    /**
     * Get default headers for requests
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
