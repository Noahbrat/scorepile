<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\User;
use Cake\Core\Configure;
use Cake\Http\Exception\UnauthorizedException;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT Authentication Trait for Controllers
 *
 * Provides manual JWT token validation for controllers that need to authenticate
 * users via JWT tokens when CakePHP's authentication middleware doesn't suffice.
 */
trait JwtAuthTrait
{
    /**
     * Manual JWT token validation
     *
     * @return \App\Model\Entity\User|null User entity if valid token, null if invalid
     */
    protected function validateJwtToken(): ?User
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
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

            $usersTable = $this->getTableLocator()->get('Users');
            $user = $usersTable->get($decoded->sub);
            assert($user instanceof User);

            return $user;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get authenticated user via CakePHP auth or manual JWT validation
     *
     * @return \App\Model\Entity\User|null
     */
    protected function getAuthenticatedUser(): ?User
    {
        if ($this->components()->has('Authentication')) {
            $identity = $this->Authentication->getIdentity();
            if ($identity) {
                assert($identity instanceof User);

                return $identity;
            }
        }

        return $this->validateJwtToken();
    }

    /**
     * Require authentication for the current request
     *
     * @return \App\Model\Entity\User User entity if authenticated
     * @throws \Cake\Http\Exception\UnauthorizedException If not authenticated
     */
    protected function requireAuthentication(): User
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            throw new UnauthorizedException('Authentication required');
        }

        return $user;
    }
}
