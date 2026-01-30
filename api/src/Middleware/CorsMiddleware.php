<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) for API endpoints.
 * Configure allowed origins in config/app.php under the 'Cors' key.
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $corsConfig = Configure::read('Cors', []);
        $allowedOrigins = $corsConfig['allowedOrigins'] ?? ['http://localhost:5173'];
        $allowedMethods = $corsConfig['allowedMethods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $allowedHeaders = $corsConfig['allowedHeaders'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $allowCredentials = $corsConfig['allowCredentials'] ?? true;
        $maxAge = $corsConfig['maxAge'] ?? 3600;

        $requestOrigin = $request->getHeaderLine('Origin');
        $allowedOrigin = $this->validateOrigin($requestOrigin, $allowedOrigins);

        // Handle OPTIONS preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            $response = $response->withType('application/json')->withStatus(200);

            if ($allowedOrigin) {
                assert($request instanceof ServerRequest);

                $corsBuilder = $response->cors($request)
                    ->allowOrigin($allowedOrigin)
                    ->allowMethods($allowedMethods)
                    ->allowHeaders($allowedHeaders);

                if ($allowCredentials) {
                    $corsBuilder = $corsBuilder->allowCredentials();
                }

                $corsBuilder = $corsBuilder->maxAge($maxAge);

                return $corsBuilder->build();
            }

            return $response;
        }

        // Process the request and add CORS headers to the response
        $response = $handler->handle($request);

        if ($allowedOrigin) {
            assert($request instanceof ServerRequest);

            $corsBuilder = $response->cors($request)
                ->allowOrigin($allowedOrigin)
                ->allowMethods($allowedMethods)
                ->allowHeaders($allowedHeaders);

            if ($allowCredentials) {
                $corsBuilder = $corsBuilder->allowCredentials();
            }

            $corsBuilder = $corsBuilder->maxAge($maxAge);

            return $corsBuilder->build();
        }

        return $response;
    }

    private function validateOrigin(string $requestOrigin, array $allowedOrigins): ?string
    {
        if (empty($requestOrigin)) {
            return null;
        }

        if (in_array($requestOrigin, $allowedOrigins, true)) {
            return $requestOrigin;
        }

        return null;
    }
}
