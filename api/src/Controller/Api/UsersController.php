<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\User;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\View\JsonView;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Users Controller
 *
 * Handles user registration, authentication (JWT), profile management,
 * and password reset flows.
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    public function initialize(): void
    {
        parent::initialize();

        $this->viewBuilder()->setOption('serialize', true);
        $this->response = $this->response->withType('application/json');

        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->Authentication->addUnauthenticatedActions([
            'register',
            'login',
            'jwtLogin',
            'jwtRefresh',
            'profile',
            'logout',
            'updateProfile',
            'changePassword',
            'forgotPassword',
            'resetPassword',
        ]);
    }

    /**
     * User registration
     */
    public function register(): void
    {
        $this->request->allowMethod(['post']);

        $user = $this->Users->newEmptyEntity();
        $user = $this->Users->patchEntity($user, $this->request->getData());

        if ($this->Users->save($user)) {
            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'created' => $user->created,
                ],
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Registration failed',
                'errors' => $user->getErrors(),
            ]);
        }
    }

    /**
     * Get current user profile via manual JWT validation
     */
    public function profile(): void
    {
        $user = $this->validateJwtToken();

        if ($user) {
            $this->set([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'active' => $user->active,
                    'is_superuser' => $user->is_superuser,
                    'role' => $user->role,
                ],
            ]);
        } else {
            $this->response = $this->response->withStatus(401);
            $this->set([
                'success' => false,
                'message' => 'Not authenticated',
            ]);
        }
    }

    /**
     * Manual JWT token validation
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

            $user = $this->Users->get($decoded->sub);
            assert($user instanceof User);

            return $user;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * JWT Login — returns access + refresh tokens
     */
    public function jwtLogin(): void
    {
        $this->request->allowMethod(['post']);

        $result = $this->Authentication->getResult();

        if ($result && $result->isValid()) {
            $user = $result->getData();

            $jwtConfig = Configure::read('Jwt');
            $jwtSecret = Configure::read('Security.jwtSecret');
            $accessTokenLifetime = $jwtConfig['AccessToken']['lifetime'];
            $refreshTokenLifetime = $jwtConfig['RefreshToken']['lifetime'];
            $accessTokenSecret = $jwtConfig['AccessToken']['secret'] ?? $jwtSecret;
            $refreshTokenSecret = $jwtConfig['RefreshToken']['secret'] ?? $accessTokenSecret;

            $accessPayload = [
                'sub' => $user->id,
                'email' => $user->email,
                'exp' => time() + $accessTokenLifetime,
                'type' => 'access',
            ];

            $refreshPayload = [
                'sub' => $user->id,
                'iat' => time(),
                'jti' => bin2hex(random_bytes(16)),
                'exp' => time() + $refreshTokenLifetime,
                'type' => 'refresh',
            ];

            $accessToken = JWT::encode($accessPayload, $accessTokenSecret, 'HS256');
            $refreshToken = JWT::encode($refreshPayload, $refreshTokenSecret, 'HS256');

            $this->set([
                'success' => true,
                'data' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => $accessTokenLifetime,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'username' => $user->username,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'active' => $user->active,
                        'is_superuser' => $user->is_superuser,
                        'role' => $user->role,
                    ],
                ],
            ]);
        } else {
            $this->response = $this->response->withStatus(401);
            $this->set([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
        }
    }

    /**
     * JWT Refresh — validates refresh token and issues new token pair
     */
    public function jwtRefresh(): void
    {
        $this->request->allowMethod(['post']);

        $refreshToken = $this->request->getData('refresh_token');

        if (!$refreshToken) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Refresh token required',
            ]);

            return;
        }

        try {
            $jwtConfig = Configure::read('Jwt');
            $jwtSecret = Configure::read('Security.jwtSecret');
            $accessTokenSecret = $jwtConfig['AccessToken']['secret'] ?? $jwtSecret;
            $refreshTokenSecret = $jwtConfig['RefreshToken']['secret'] ?? $accessTokenSecret;

            $decoded = JWT::decode($refreshToken, new Key($refreshTokenSecret, 'HS256'));

            if ($decoded->type !== 'refresh') {
                throw new Exception('Invalid token type');
            }

            $user = $this->Users->get($decoded->sub);

            $accessTokenLifetime = $jwtConfig['AccessToken']['lifetime'];
            $refreshTokenLifetime = $jwtConfig['RefreshToken']['lifetime'];

            $accessPayload = [
                'sub' => $user->id,
                'email' => $user->email,
                'exp' => time() + $accessTokenLifetime,
                'type' => 'access',
            ];
            $accessToken = JWT::encode($accessPayload, $accessTokenSecret, 'HS256');

            $refreshPayload = [
                'sub' => $user->id,
                'iat' => time(),
                'jti' => bin2hex(random_bytes(16)),
                'exp' => time() + $refreshTokenLifetime,
                'type' => 'refresh',
            ];
            $newRefreshToken = JWT::encode($refreshPayload, $refreshTokenSecret, 'HS256');

            $this->set([
                'success' => true,
                'data' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $newRefreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => $accessTokenLifetime,
                ],
            ]);
        } catch (Exception $e) {
            $this->response = $this->response->withStatus(401);
            $this->set([
                'success' => false,
                'message' => 'Invalid refresh token',
            ]);
        }
    }

    /**
     * User logout
     */
    public function logout(): void
    {
        $this->request->allowMethod(['post']);

        $this->set([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(): void
    {
        $this->request->allowMethod(['put', 'patch']);

        $user = $this->validateJwtToken();

        if (!$user) {
            $this->response = $this->response->withStatus(401);
            $this->set([
                'success' => false,
                'message' => 'Not authenticated',
            ]);

            return;
        }

        $user = $this->Users->get($user->id);

        $allowedFields = ['email', 'username', 'first_name', 'last_name'];
        $data = [];
        foreach ($allowedFields as $field) {
            if ($this->request->getData($field) !== null) {
                $data[$field] = $this->request->getData($field);
            }
        }

        if (empty($data)) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'No valid fields provided for update',
            ]);

            return;
        }

        $user = $this->Users->patchEntity($user, $data);

        if ($this->Users->save($user)) {
            $this->set([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'active' => $user->active,
                    'is_superuser' => $user->is_superuser,
                    'role' => $user->role,
                ],
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Profile update failed',
                'errors' => $user->getErrors(),
            ]);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(): void
    {
        $this->request->allowMethod(['put', 'patch']);

        $user = $this->validateJwtToken();

        if (!$user) {
            $this->response = $this->response->withStatus(401);
            $this->set([
                'success' => false,
                'message' => 'Not authenticated',
            ]);

            return;
        }

        $currentPassword = $this->request->getData('current_password');
        $newPassword = $this->request->getData('new_password');

        if (!$currentPassword || !$newPassword) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Both current and new passwords are required',
            ]);

            return;
        }

        $user = $this->Users->get($user->id);

        $hasher = new DefaultPasswordHasher();
        if (!$hasher->check($currentPassword, $user->password)) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Current password is incorrect',
            ]);

            return;
        }

        $user->password = $newPassword;

        if ($this->Users->save($user)) {
            $this->set([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Password change failed',
                'errors' => $user->getErrors(),
            ]);
        }
    }

    /**
     * Initiate password reset
     */
    public function forgotPassword(): void
    {
        $this->request->allowMethod(['post']);

        // Always return success to prevent email enumeration
        $this->set([
            'success' => true,
            'message' => 'If an account with this email exists, a password reset link has been sent.',
        ]);
    }

    /**
     * Complete password reset with token
     */
    public function resetPassword(): void
    {
        $this->request->allowMethod(['post']);

        $token = $this->request->getData('token');
        $newPassword = $this->request->getData('password');

        if (!$token || !$newPassword) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Token and new password are required',
            ]);

            return;
        }

        // TODO: Implement token validation against database
        $this->response = $this->response->withStatus(400);
        $this->set([
            'success' => false,
            'message' => 'Invalid or expired reset token',
        ]);
    }
}
