<?php
declare(strict_types=1);

namespace App\Error;

use Cake\Controller\Controller;
use Cake\Error\Renderer\WebExceptionRenderer;

/**
 * Custom exception renderer that forces JSON responses for all errors.
 * Ensures consistent API error formatting.
 */
class AppExceptionRenderer extends WebExceptionRenderer
{
    protected function _getController(): Controller
    {
        $controller = parent::_getController();

        $controller->setResponse($controller->getResponse()->withType('application/json'));
        $controller->viewBuilder()->setClassName('Json');

        return $controller;
    }
}
