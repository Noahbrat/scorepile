<?php
declare(strict_types=1);

/**
 * Test suite bootstrap - loads CakePHP application for testing
 */
require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Core\Configure;

Configure::write('debug', true);

// Ensure a JWT secret is available for tests
if (!Configure::read('Security.jwtSecret')) {
    Configure::write('Security.jwtSecret', 'test-jwt-secret-key-for-testing-only');
}
