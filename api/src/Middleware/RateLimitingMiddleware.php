<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Log\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rate Limiting Middleware
 *
 * Provides request rate limiting using a sliding window algorithm.
 * Configure rate limits per endpoint type in config/app.php under 'RateLimit'.
 *
 * By default, only rate-limits auth endpoints (login, register, refresh).
 * Add your own endpoint types in getEndpointType() as needed.
 */
class RateLimitingMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $endpointType = $this->getEndpointType($request);

        if (!$endpointType) {
            return $handler->handle($request);
        }

        $userId = $this->getClientIp($request);
        $rateLimitConfig = $this->getRateLimitConfig($endpointType);

        $controller = $request->getParam('controller', 'unknown');
        $action = $request->getParam('action', 'unknown');
        $endpoint = "{$controller}::{$action}";

        $rateLimitResult = $this->checkRateLimit($userId, $endpoint, $rateLimitConfig);

        if (!$rateLimitResult['allowed']) {
            return $this->createRateLimitExceededResponse($rateLimitResult);
        }

        $response = $handler->handle($request);

        return $this->addRateLimitHeaders($response, $rateLimitResult);
    }

    /**
     * Determine endpoint type for rate limiting.
     * Override this to add rate limiting to your own endpoints.
     */
    private function getEndpointType(ServerRequestInterface $request): ?string
    {
        if ($request->getMethod() !== 'POST') {
            return null;
        }

        $controller = $request->getParam('controller');
        $action = $request->getParam('action');

        if ($controller === 'Users') {
            if ($action === 'jwtLogin') {
                return 'jwt_login';
            }
            if ($action === 'jwtRefresh') {
                return 'jwt_refresh';
            }
            if ($action === 'register') {
                return 'register';
            }
        }

        return null;
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                return $ip;
            }
        }

        return '127.0.0.1';
    }

    private function getRateLimitConfig(string $endpointType): array
    {
        $config = Configure::read("RateLimit.{$endpointType}", []);

        return [
            'requests_per_minute' => $config['requests_per_minute'] ?? 5,
            'requests_per_hour' => $config['requests_per_hour'] ?? 20,
            'burst_allowance' => $config['burst_allowance'] ?? 2,
        ];
    }

    private function checkRateLimit(string $identifier, string $endpoint, array $config): array
    {
        $cacheConfig = Configure::read('RateLimit.cache_config', 'default');

        if (!in_array($cacheConfig, Cache::configured(), true)) {
            $cacheConfig = 'default';
        }

        $now = time();
        $minuteKey = "rate_limit:{$identifier}:{$endpoint}:minute:" . floor($now / 60);
        $hourKey = "rate_limit:{$identifier}:{$endpoint}:hour:" . floor($now / 3600);

        $minuteCount = (int)Cache::read($minuteKey, $cacheConfig) ?: 0;
        $hourCount = (int)Cache::read($hourKey, $cacheConfig) ?: 0;

        $minuteLimit = (int)$config['requests_per_minute'];
        $hourLimit = (int)$config['requests_per_hour'];
        $burstAllowance = (int)$config['burst_allowance'];
        $effectiveMinuteLimit = $minuteLimit + $burstAllowance;

        if ($minuteCount >= $effectiveMinuteLimit || $hourCount >= $hourLimit) {
            return [
                'allowed' => false,
                'minute_limit' => $minuteLimit,
                'hour_limit' => $hourLimit,
                'minute_remaining' => max(0, $effectiveMinuteLimit - $minuteCount),
                'hour_remaining' => max(0, $hourLimit - $hourCount),
                'reset_time' => (int)((floor($now / 60) + 1) * 60),
                'retry_after' => (int)((floor($now / 60) + 1) * 60) - $now,
            ];
        }

        Cache::write($minuteKey, $minuteCount + 1, $cacheConfig);
        Cache::write($hourKey, $hourCount + 1, $cacheConfig);

        return [
            'allowed' => true,
            'minute_limit' => $minuteLimit,
            'hour_limit' => $hourLimit,
            'minute_remaining' => max(0, $effectiveMinuteLimit - $minuteCount - 1),
            'hour_remaining' => max(0, $hourLimit - $hourCount - 1),
            'reset_time' => (int)((floor($now / 60) + 1) * 60),
            'retry_after' => null,
        ];
    }

    private function createRateLimitExceededResponse(array $rateLimitResult): ResponseInterface
    {
        $response = new Response(['status' => 429, 'type' => 'application/json']);

        $response = $response
            ->withHeader('X-RateLimit-Limit-Minute', (string)$rateLimitResult['minute_limit'])
            ->withHeader('X-RateLimit-Remaining-Minute', (string)$rateLimitResult['minute_remaining'])
            ->withHeader('Retry-After', (string)$rateLimitResult['retry_after']);

        $body = json_encode([
            'success' => false,
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after_seconds' => $rateLimitResult['retry_after'],
        ]);
        assert($body !== false);
        $response->getBody()->write($body);

        Log::warning('Rate limit exceeded');

        return $response;
    }

    private function addRateLimitHeaders(ResponseInterface $response, array $rateLimitResult): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit-Minute', (string)$rateLimitResult['minute_limit'])
            ->withHeader('X-RateLimit-Remaining-Minute', (string)$rateLimitResult['minute_remaining'])
            ->withHeader('X-RateLimit-Reset', (string)$rateLimitResult['reset_time']);
    }
}
