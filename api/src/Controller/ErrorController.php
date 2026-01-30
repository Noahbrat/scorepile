<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * Error Handling Controller
 *
 * Renders error responses as JSON for the API.
 */
class ErrorController extends AppController
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->loadComponent('RequestHandler');
    }

    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
    }

    /**
     * beforeRender callback — force JSON error responses.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null
     */
    public function beforeRender(EventInterface $event): ?Response
    {
        $this->setResponse($this->getResponse()->withType('application/json'));
        $this->viewBuilder()->setClassName('Json');

        parent::beforeRender($event);

        $exception = $this->getRequest()->getAttribute('exception');
        $error = [
            'success' => false,
            'message' => $exception ? $exception->getMessage() : 'An error occurred',
        ];

        if ($exception) {
            $error['error'] = [
                'type' => get_class($exception),
                'code' => $exception->getCode(),
            ];
        }

        $this->set($error);
        $this->viewBuilder()->setOption('serialize', array_keys($error));

        return null;
    }

    /**
     * afterFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return void
     */
    public function afterFilter(EventInterface $event): void
    {
    }
}
