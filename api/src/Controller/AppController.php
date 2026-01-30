<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;

/**
 * Application Controller
 *
 * Base controller for the application. All controllers inherit from this.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
    }

    /**
     * Before filter callback.
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Set JSON response type for API requests
        if (str_starts_with($this->getRequest()->getPath(), '/api/')) {
            $this->setResponse($this->getResponse()->withType('application/json'));
            $this->viewBuilder()->setClassName('Json');

            // Allow unauthenticated access to read endpoints by default
            if ($this->components()->has('Authentication')) {
                $this->Authentication->addUnauthenticatedActions(['index', 'view']);
            }
        }
    }
}
