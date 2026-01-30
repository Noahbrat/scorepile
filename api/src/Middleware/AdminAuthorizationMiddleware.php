<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Model\Entity\User;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin Authorization Middleware
 *
 * Protects admin routes by ensuring only users with admin privileges
 * can perform write operations. Read access (GET) is public by default.
 *
 * Configure admin route patterns in isAdminRoute() for your application.
 */
class AdminAuthorizationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!$this->isAdminRoute($path)) {
            return $handler->handle($request);
        }

        // Allow public GET access to admin-managed reference data
        if ($request->getMethod() === 'GET') {
            return $handler->handle($request);
        }

        // For write operations, require admin auth
        $user = $this->getUserFromJwt($request);

        if (!$user) {
            throw new UnauthorizedException('Authentication required for write operations');
        }

        if (!$user->canAccessAdmin()) {
            throw new ForbiddenException('Admin privileges required');
        }

        $request = $request->withAttribute('authenticatedUser', $user);

        return $handler->handle($request);
    }

    /**
     * Check if the current path is an admin route.
     *
     * Customize these patterns for your application's admin resources.
     */
    private function isAdminRoute(string $path): bool
    {
        $adminApiPatterns = [
            // Add patterns for routes that require admin for writes:
            // '#^/api/categories($|/|\.)#',
        ];

        foreach ($adminApiPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function getUserFromJwt(ServerRequestInterface $request): ?User
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = null;

        if (preg_match('/Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            return null;
        }

        try {
            $jwtSecret = Configure::read('Security.jwtSecret');
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));

            if ($decoded->type !== 'access') {
                return null;
            }

            $usersTable = TableRegistry::getTableLocator()->get('Users');
            $user = $usersTable->get($decoded->sub);
            assert($user instanceof User);

            return $user;
        } catch (Exception $e) {
            return null;
        }
    }
}
