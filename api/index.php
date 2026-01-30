<?php
/**
 * Front controller for the API
 *
 * This is the entry point for all API requests.
 */
require dirname(__DIR__) . '/api/config/paths.php';
require CORE_PATH . 'config' . DS . 'bootstrap.php';

use App\Application;
use Cake\Http\Server;

$server = new Server(new Application(CONFIG));
$server->emit($server->run());
