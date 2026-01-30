<?php
declare(strict_types=1);

namespace App;

use App\Middleware\AdminAuthorizationMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitingMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * Defines the bootstrapping logic and middleware layers for the API.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        if (PHP_SAPI === 'cli') {
            $this->bootstrapCli();
        }

        if (Configure::read('debug')) {
            $this->addPlugin('DebugKit');
        }

        // Load Authentication plugin
        $this->addPlugin('Authentication');

        // Configure JWT settings
        Configure::write('Jwt', [
            'enabled' => true,
            'AccessToken' => [
                'lifetime' => 600, // 10 minutes
                'secret' => Configure::read('Security.jwtSecret'),
            ],
            'RefreshToken' => [
                'lifetime' => 2 * 604800, // 2 weeks
                'secret' => Configure::read('Security.jwtSecret'),
            ],
        ]);
    }

    /**
     * Setup the middleware queue.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Error handling
            ->add(new ErrorHandlerMiddleware(Configure::read('Error')))

            // Handle plugin/theme assets
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // CORS middleware — BEFORE routing for OPTIONS preflight
            ->add(new CorsMiddleware())

            // Routing
            ->add(new RoutingMiddleware($this))

            // Parse JSON/form request bodies
            ->add(new BodyParserMiddleware())

            // JWT + Form authentication
            ->add(new AuthenticationMiddleware($this))

            // Rate limiting (configurable per-endpoint)
            ->add(new RateLimitingMiddleware())

            // Admin authorization (must come after authentication)
            ->add(new AdminAuthorizationMiddleware());

        return $middlewareQueue;
    }

    /**
     * Returns the authentication service provider.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $authenticationService = new AuthenticationService();

        $passwordIdentifier = [
            'Authentication.Password' => [
                'fields' => [
                    'username' => 'email',
                    'password' => 'password',
                ],
                'resolver' => [
                    'className' => 'Authentication.Orm',
                    'userModel' => 'Users',
                    'finder' => 'all',
                ],
            ],
        ];

        // JWT authenticator for API access
        $authenticationService->loadAuthenticator('Authentication.Jwt', [
            'secret' => Configure::read('Security.jwtSecret'),
            'algorithm' => 'HS256',
            'returnPayload' => true,
            'identifier' => $passwordIdentifier,
        ]);

        // Form authenticator for login endpoint
        $authenticationService->loadAuthenticator('Authentication.Form', [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
            'loginUrl' => [
                '/api/users/login',
                '/api/users/jwt_login',
            ],
            'identifier' => $passwordIdentifier,
        ]);

        return $authenticationService;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
    }

    /**
     * Bootstrapping for CLI application.
     *
     * @return void
     */
    protected function bootstrapCli(): void
    {
        $this->addOptionalPlugin('Cake/Repl');
        $this->addOptionalPlugin('Bake');
        $this->addPlugin('Migrations');
    }
}
