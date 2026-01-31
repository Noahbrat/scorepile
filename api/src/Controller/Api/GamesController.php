<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\JwtAuthTrait;
use App\Controller\Trait\CrudErrorSerializationTrait;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\View\JsonView;

/**
 * Games Controller
 *
 * @property \App\Model\Table\GamesTable $Games
 */
class GamesController extends AppController
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
     * Index method — list games for authenticated user
     *
     * GET /api/games.json
     */
    public function index(): void
    {
        $user = $this->requireAuthentication();

        $query = $this->Games->find()
            ->where(['Games.user_id' => $user->id])
            ->contain(['GameTypes', 'GamePlayers' => ['Players']]);

        // Pagination
        $page = (int)($this->request->getQuery('page') ?? 1);
        $limit = min((int)($this->request->getQuery('limit') ?? 20), 100);

        // Sorting
        $sort = $this->request->getQuery('sort', 'modified');
        $direction = $this->request->getQuery('direction', 'desc');
        $allowedSortFields = ['name', 'status', 'created', 'modified'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'modified';
        }
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';
        $query->orderBy(["Games.{$sort}" => $direction]);

        // Search
        $search = $this->request->getQuery('search');
        if ($search) {
            $query->where([
                'OR' => [
                    'Games.name LIKE' => "%{$search}%",
                    'Games.notes LIKE' => "%{$search}%",
                ],
            ]);
        }

        // Status filter
        $status = $this->request->getQuery('status');
        if ($status && in_array($status, ['active', 'completed', 'abandoned'])) {
            $query->where(['Games.status' => $status]);
        }

        $total = $query->count();
        $games = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $this->set([
            'success' => true,
            'data' => $games,
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
     * View method — get single game with all details
     *
     * GET /api/games/:id.json
     */
    public function view(?string $id = null): void
    {
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id, contain: [
            'GameTypes',
            'GamePlayers' => ['Players', 'Scores'],
            'Rounds' => ['Scores'],
        ]);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to view this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $this->set([
            'success' => true,
            'data' => $game,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Add method — create new game
     *
     * POST /api/games.json
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->newEmptyEntity();
        $data = $this->request->getData();
        $data['user_id'] = $user->id;

        $game = $this->Games->patchEntity($game, $data);

        if ($this->Games->save($game)) {
            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'data' => $game,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not save game',
                'errors' => $game->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Edit method — update existing game
     *
     * PUT /api/games/:id.json
     */
    public function edit(?string $id = null): void
    {
        $this->request->allowMethod(['put', 'patch']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to edit this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $game = $this->Games->patchEntity($game, $this->request->getData());

        if ($this->Games->save($game)) {
            $this->set([
                'success' => true,
                'data' => $game,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not update game',
                'errors' => $game->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Delete method — remove game
     *
     * DELETE /api/games/:id.json
     */
    public function delete(?string $id = null): void
    {
        $this->request->allowMethod(['delete']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to delete this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($this->Games->delete($game)) {
            $this->set([
                'success' => true,
                'message' => 'Game deleted',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not delete game',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    /**
     * Complete method — mark a game as completed, calculate final ranks
     *
     * POST /api/games/:id/complete.json
     */
    public function complete(?string $id = null): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id, contain: [
            'GamePlayers',
            'GameTypes',
        ]);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to complete this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($game->status === 'completed') {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Game is already completed',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Determine scoring direction
        $scoringDirection = 'high_wins';
        if ($game->game_type && $game->game_type->scoring_direction) {
            $scoringDirection = $game->game_type->scoring_direction;
        }

        // Sort game players by total_score to determine ranks
        $gamePlayers = $game->game_players;
        usort($gamePlayers, function ($a, $b) use ($scoringDirection) {
            if ($scoringDirection === 'low_wins') {
                return $a->total_score <=> $b->total_score;
            }

            return $b->total_score <=> $a->total_score;
        });

        // Assign ranks and winner
        $gamePlayersTable = $this->getTableLocator()->get('GamePlayers');
        foreach ($gamePlayers as $rank => $gamePlayer) {
            $gamePlayer->final_rank = $rank + 1;
            $gamePlayer->is_winner = ($rank === 0);
            $gamePlayersTable->save($gamePlayer);
        }

        // Update game status
        $game->status = 'completed';
        $game->completed_at = new DateTime();

        if ($this->Games->save($game)) {
            // Re-fetch with full data
            $game = $this->Games->get($id, contain: [
                'GameTypes',
                'GamePlayers' => ['Players'],
            ]);

            $this->set([
                'success' => true,
                'data' => $game,
                'message' => 'Game completed',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not complete game',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message']);
    }
}
