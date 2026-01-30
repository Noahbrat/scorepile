<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseAppController;
use Cake\Event\EventInterface;
use Cake\View\JsonView;

/**
 * API App Controller
 *
 * All API controllers should extend this controller to ensure
 * consistent JSON responses and proper view class setup.
 */
class AppController extends BaseAppController
{
    /**
     * Specify view classes for different request types
     */
    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    /**
     * Initialize method
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->viewBuilder()->setOption('serialize', true);
        $this->response = $this->response->withType('application/json');
    }

    /**
     * BeforeFilter method
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Allow unauthenticated access to read operations by default
        if ($this->components()->has('Authentication')) {
            $this->Authentication->addUnauthenticatedActions([
                'index', 'view',
            ]);
        }
    }
}
