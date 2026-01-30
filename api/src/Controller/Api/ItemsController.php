<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\JwtAuthTrait;
use App\Controller\Trait\CrudErrorSerializationTrait;
use Cake\Event\EventInterface;
use Cake\View\JsonView;

/**
 * Items Controller
 *
 * Example CRUD resource controller demonstrating the pattern.
 * Copy this controller and adapt it for your own resources.
 *
 * @property \App\Model\Table\ItemsTable $Items
 */
class ItemsController extends AppController
{
    use JwtAuthTrait;
    use CrudErrorSerializationTrait;

    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');

        // Load CRUD plugin for automatic RESTful actions
        $this->loadComponent('Crud.Crud', [
            'actions' => [
                'Crud.Index',
                'Crud.View',
                'Crud.Add',
                'Crud.Edit',
                'Crud.Delete',
            ],
            'listeners' => [
                'Crud.Api',
                'Crud.ApiPagination',
            ],
        ]);

        $this->configureCrudErrorSerialization();
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Allow unauthenticated access to read operations
        $this->Authentication->addUnauthenticatedActions(['index', 'view']);
    }

    /**
     * Index method — list items for authenticated user
     *
     * GET /api/items.json
     */
    public function index(): void
    {
        // Optional: scope items to authenticated user
        $user = $this->getAuthenticatedUser();

        $query = $this->Items->find();

        if ($user) {
            $query->where(['Items.user_id' => $user->id]);
        }

        // Pagination
        $page = (int)($this->request->getQuery('page') ?? 1);
        $limit = min((int)($this->request->getQuery('limit') ?? 20), 100);

        // Sorting
        $sort = $this->request->getQuery('sort', 'modified');
        $direction = $this->request->getQuery('direction', 'desc');
        $allowedSortFields = ['title', 'created', 'modified', 'status'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'modified';
        }
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';
        $query->orderBy(["Items.{$sort}" => $direction]);

        // Search
        $search = $this->request->getQuery('search');
        if ($search) {
            $query->where([
                'OR' => [
                    'Items.title LIKE' => "%{$search}%",
                    'Items.description LIKE' => "%{$search}%",
                ],
            ]);
        }

        $total = $query->count();
        $items = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $this->set([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'pagination']);
    }

    /**
     * View method — get single item
     *
     * GET /api/items/:id.json
     */
    public function view(?string $id = null): void
    {
        $item = $this->Items->get($id);

        $this->set([
            'success' => true,
            'data' => $item,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Add method — create new item (requires auth)
     *
     * POST /api/items.json
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $item = $this->Items->newEmptyEntity();
        $data = $this->request->getData();
        $data['user_id'] = $user->id;

        $item = $this->Items->patchEntity($item, $data);

        if ($this->Items->save($item)) {
            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'data' => $item,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not save item',
                'errors' => $item->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Edit method — update existing item (requires auth + ownership)
     *
     * PUT /api/items/:id.json
     */
    public function edit(?string $id = null): void
    {
        $this->request->allowMethod(['put', 'patch']);
        $user = $this->requireAuthentication();

        $item = $this->Items->get($id);

        // Verify ownership
        if ($item->user_id !== $user->id && !$user->isAdmin()) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to edit this item',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $item = $this->Items->patchEntity($item, $this->request->getData());

        if ($this->Items->save($item)) {
            $this->set([
                'success' => true,
                'data' => $item,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not update item',
                'errors' => $item->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Delete method — remove item (requires auth + ownership)
     *
     * DELETE /api/items/:id.json
     */
    public function delete(?string $id = null): void
    {
        $this->request->allowMethod(['delete']);
        $user = $this->requireAuthentication();

        $item = $this->Items->get($id);

        // Verify ownership
        if ($item->user_id !== $user->id && !$user->isAdmin()) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to delete this item',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($this->Items->delete($item)) {
            $this->set([
                'success' => true,
                'message' => 'Item deleted',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not delete item',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }
}
