<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogApiRequests
{
    /**
     * Handle an incoming request and log API activity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        // Log incoming request
        Log::info('API Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->filterSensitiveHeaders($request->headers->all()),
            'payload' => $this->filterSensitiveData($request->all()),
            'timestamp' => now()->toISOString(),
        ]);

        $response = $next($request);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // Log response
        Log::info('API Response', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'execution_time_ms' => $executionTime,
            'response_size' => strlen($response->getContent()),
            'timestamp' => now()->toISOString(),
        ]);

        return $response;
    }

    /**
     * Filter sensitive headers from logging
     *
     * @param array $headers
     * @return array
     */
    private function filterSensitiveHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
            'set-cookie',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = '[FILTERED]';
            }
        }

        return $headers;
    }

    /**
     * Filter sensitive data from request payload
     *
     * @param array $data
     * @return array
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'access_token',
            'api_key',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[FILTERED]';
            }
        }

        return $data;
    }
}