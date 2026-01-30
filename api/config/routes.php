<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are important for connecting URLs to controllers and actions.
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes) {
    $routes->setRouteClass(DashedRoute::class);

    // Parse JSON/XML extensions globally
    $routes->setExtensions(['json', 'xml']);

    // API routes
    $routes->prefix('api', function (RouteBuilder $routes) {

        // =====================================================
        // Example CRUD Resource: Items
        // =====================================================
        $routes->resources('Items', [
            'controller' => 'Api/Items',
        ]);

        // =====================================================
        // Authentication Routes
        // =====================================================
        $routes->resources('Users', [
            'controller' => 'Api/Users',
        ]);

        $routes->connect('/users/register', [
            'controller' => 'Users',
            'action' => 'register',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        $routes->connect('/users/login', [
            'controller' => 'Users',
            'action' => 'login',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        $routes->connect('/users/profile', [
            'controller' => 'Users',
            'action' => 'profile',
            'prefix' => 'Api',
        ], ['_method' => ['GET']]);

        $routes->connect('/users/logout', [
            'controller' => 'Users',
            'action' => 'logout',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        $routes->connect('/users/jwt_login', [
            'controller' => 'Users',
            'action' => 'jwtLogin',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        $routes->connect('/users/jwt_refresh', [
            'controller' => 'Users',
            'action' => 'jwtRefresh',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        // Profile management
        $routes->connect('/users/update-profile', [
            'controller' => 'Users',
            'action' => 'updateProfile',
            'prefix' => 'Api',
        ], ['_method' => ['PUT', 'PATCH']]);

        $routes->connect('/users/change-password', [
            'controller' => 'Users',
            'action' => 'changePassword',
            'prefix' => 'Api',
        ], ['_method' => ['PUT', 'PATCH']]);

        // Password reset
        $routes->connect('/users/forgot-password', [
            'controller' => 'Users',
            'action' => 'forgotPassword',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        $routes->connect('/users/reset-password', [
            'controller' => 'Users',
            'action' => 'resetPassword',
            'prefix' => 'Api',
        ], ['_method' => ['POST']]);

        // =====================================================
        // Add your own resource routes here:
        // =====================================================
        // $routes->resources('YourResource', [
        //     'controller' => 'Api/YourResource',
        // ]);
    });

    // No traditional web routes — this is an API-only backend.
    // The Vue.js frontend is served by the Vite dev server or built to a static host.
};
