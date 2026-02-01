<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\JwtAuthTrait;
use App\Controller\Trait\CrudErrorSerializationTrait;
use Cake\Event\EventInterface;
use Cake\View\JsonView;

/**
 * GameTypes Controller
 *
 * @property \App\Model\Table\GameTypesTable $GameTypes
 */
class GameTypesController extends AppController
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

        $this->Authentication->addUnauthenticatedActions([]);
    }

    /**
     * Index method — list game types for authenticated user
     *
     * GET /api/game-types.json
     */
    public function index(): void
    {
        $user = $this->requireAuthentication();

        $query = $this->GameTypes->find()
            ->where([
                'OR' => [
                    'GameTypes.user_id' => $user->id,
                    'GameTypes.is_system' => true,
                ],
            ]);

        // Pagination
        $page = (int)($this->request->getQuery('page') ?? 1);
        $limit = min((int)($this->request->getQuery('limit') ?? 20), 100);

        // Sorting
        $sort = $this->request->getQuery('sort', 'name');
        $direction = $this->request->getQuery('direction', 'asc');
        $allowedSortFields = ['name', 'scoring_direction', 'created', 'modified'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'name';
        }
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'asc';
        $query->orderBy(["GameTypes.{$sort}" => $direction]);

        // Search
        $search = $this->request->getQuery('search');
        if ($search) {
            $query->where([
                'OR' => [
                    'GameTypes.name LIKE' => "%{$search}%",
                    'GameTypes.description LIKE' => "%{$search}%",
                ],
            ]);
        }

        $total = $query->count();
        $gameTypes = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $this->set([
            'success' => true,
            'data' => $gameTypes,
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
     * View method — get single game type
     *
     * GET /api/game-types/:id.json
     */
    public function view(?string $id = null): void
    {
        $user = $this->requireAuthentication();

        $gameType = $this->GameTypes->get($id);

        if ($gameType->user_id !== $user->id && !$gameType->is_system) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to view this game type',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $this->set([
            'success' => true,
            'data' => $gameType,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Add method — create new game type
     *
     * POST /api/game-types.json
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $gameType = $this->GameTypes->newEmptyEntity();
        $data = $this->request->getData();
        $data['user_id'] = $user->id;

        $gameType = $this->GameTypes->patchEntity($gameType, $data);

        if ($this->GameTypes->save($gameType)) {
            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'data' => $gameType,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not save game type',
                'errors' => $gameType->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Edit method — update existing game type
     *
     * PUT /api/game-types/:id.json
     */
    public function edit(?string $id = null): void
    {
        $this->request->allowMethod(['put', 'patch']);
        $user = $this->requireAuthentication();

        $gameType = $this->GameTypes->get($id);

        if ($gameType->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to edit this game type',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $gameType = $this->GameTypes->patchEntity($gameType, $this->request->getData());

        if ($this->GameTypes->save($gameType)) {
            $this->set([
                'success' => true,
                'data' => $gameType,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not update game type',
                'errors' => $gameType->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Delete method — remove game type
     *
     * DELETE /api/game-types/:id.json
     */
    public function delete(?string $id = null): void
    {
        $this->request->allowMethod(['delete']);
        $user = $this->requireAuthentication();

        $gameType = $this->GameTypes->get($id);

        if ($gameType->is_system) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'System game types cannot be deleted',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($gameType->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to delete this game type',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($this->GameTypes->delete($gameType)) {
            $this->set([
                'success' => true,
                'message' => 'Game type deleted',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not delete game type',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }
}
