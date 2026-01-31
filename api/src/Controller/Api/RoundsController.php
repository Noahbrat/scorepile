<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\JwtAuthTrait;
use App\Controller\Trait\CrudErrorSerializationTrait;
use Cake\Event\EventInterface;
use Cake\View\JsonView;

/**
 * Rounds Controller
 *
 * @property \App\Model\Table\RoundsTable $Rounds
 */
class RoundsController extends AppController
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
     * Index method — list rounds for a game
     *
     * GET /api/rounds.json?game_id=:game_id
     */
    public function index(): void
    {
        $user = $this->requireAuthentication();

        $gameId = $this->request->getQuery('game_id');
        if (!$gameId) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'game_id is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Verify game ownership
        $gamesTable = $this->getTableLocator()->get('Games');
        $game = $gamesTable->get($gameId);
        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to view rounds for this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $rounds = $this->Rounds->find()
            ->where(['Rounds.game_id' => $gameId])
            ->contain(['Scores' => ['GamePlayers' => ['Players']]])
            ->orderBy(['Rounds.round_number' => 'ASC'])
            ->all();

        $this->set([
            'success' => true,
            'data' => $rounds,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * View method — get single round with scores
     *
     * GET /api/rounds/:id.json
     */
    public function view(?string $id = null): void
    {
        $user = $this->requireAuthentication();

        $round = $this->Rounds->get($id, contain: [
            'Games',
            'Scores' => ['GamePlayers' => ['Players']],
        ]);

        if ($round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to view this round',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $this->set([
            'success' => true,
            'data' => $round,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Add method — add a round to a game
     *
     * POST /api/rounds.json
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $data = $this->request->getData();

        // Verify game ownership
        if (empty($data['game_id'])) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'game_id is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $gamesTable = $this->getTableLocator()->get('Games');
        $game = $gamesTable->get($data['game_id']);
        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to add rounds to this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Auto-assign round_number if not provided
        if (empty($data['round_number'])) {
            $maxRound = $this->Rounds->find()
                ->where(['game_id' => $data['game_id']])
                ->select(['max_round' => $this->Rounds->find()->func()->max('round_number')])
                ->first();
            $data['round_number'] = ($maxRound->max_round ?? 0) + 1;
        }

        $round = $this->Rounds->newEmptyEntity();
        $round = $this->Rounds->patchEntity($round, $data);

        if ($this->Rounds->save($round)) {
            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'data' => $round,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not save round',
                'errors' => $round->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Edit method — update a round
     *
     * PUT /api/rounds/:id.json
     */
    public function edit(?string $id = null): void
    {
        $this->request->allowMethod(['put', 'patch']);
        $user = $this->requireAuthentication();

        $round = $this->Rounds->get($id, contain: ['Games']);

        if ($round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to edit this round',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $round = $this->Rounds->patchEntity($round, $this->request->getData());

        if ($this->Rounds->save($round)) {
            $this->set([
                'success' => true,
                'data' => $round,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not update round',
                'errors' => $round->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Delete method — remove a round
     *
     * DELETE /api/rounds/:id.json
     */
    public function delete(?string $id = null): void
    {
        $this->request->allowMethod(['delete']);
        $user = $this->requireAuthentication();

        $round = $this->Rounds->get($id, contain: ['Games']);

        if ($round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to delete this round',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($this->Rounds->delete($round)) {
            $this->set([
                'success' => true,
                'message' => 'Round deleted',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not delete round',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }
}
