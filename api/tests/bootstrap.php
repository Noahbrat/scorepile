<?php
declare(strict_types=1);

/**
 * Test suite bootstrap
 */
require dirname(__DIR__) . '/config/paths.php';
require CORE_PATH . 'config' . DS . 'bootstrap.php';

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

// Use test database
if (!ConnectionManager::getConfig('test')) {
    ConnectionManager::setConfig('test', [
        'url' => env('DATABASE_TEST_URL', 'sqlite://127.0.0.1/tests.sqlite'),
    ]);
}

Configure::write('debug', true);
