<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\JwtAuthTrait;
use App\Controller\Trait\CrudErrorSerializationTrait;
use Cake\Event\EventInterface;
use Cake\View\JsonView;

/**
 * Scores Controller
 *
 * @property \App\Model\Table\ScoresTable $Scores
 */
class ScoresController extends AppController
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
     * Index method — list scores for a round
     *
     * GET /api/scores.json?round_id=:round_id
     */
    public function index(): void
    {
        $user = $this->requireAuthentication();

        $roundId = $this->request->getQuery('round_id');
        if (!$roundId) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'round_id is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Verify ownership through round -> game -> user
        $roundsTable = $this->getTableLocator()->get('Rounds');
        $round = $roundsTable->get($roundId, contain: ['Games']);
        if ($round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to view scores for this round',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $scores = $this->Scores->find()
            ->where(['Scores.round_id' => $roundId])
            ->contain(['GamePlayers' => ['Players']])
            ->all();

        $this->set([
            'success' => true,
            'data' => $scores,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * View method — get single score
     *
     * GET /api/scores/:id.json
     */
    public function view(?string $id = null): void
    {
        $user = $this->requireAuthentication();

        $score = $this->Scores->get($id, contain: [
            'Rounds' => ['Games'],
            'GamePlayers' => ['Players'],
        ]);

        if ($score->round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to view this score',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $this->set([
            'success' => true,
            'data' => $score,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Add method — add score(s) for a round (bulk-friendly)
     *
     * POST /api/scores.json
     *
     * Accepts either a single score object or an array of scores under the "scores" key.
     * Single: { "round_id": 1, "game_player_id": 1, "points": 50 }
     * Bulk:   { "scores": [{ "round_id": 1, "game_player_id": 1, "points": 50 }, ...] }
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $data = $this->request->getData();

        // Handle bulk scores
        if (isset($data['scores']) && is_array($data['scores'])) {
            $this->addBulk($user, $data['scores']);

            return;
        }

        // Single score
        $this->addSingle($user, $data);
    }

    /**
     * Edit method — update a score
     *
     * PUT /api/scores/:id.json
     */
    public function edit(?string $id = null): void
    {
        $this->request->allowMethod(['put', 'patch']);
        $user = $this->requireAuthentication();

        $score = $this->Scores->get($id, contain: [
            'Rounds' => ['Games'],
        ]);

        if ($score->round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to edit this score',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $score = $this->Scores->patchEntity($score, $this->request->getData());

        if ($this->Scores->save($score)) {
            // Recalculate game player total
            $this->recalculateTotal($score->game_player_id);

            $this->set([
                'success' => true,
                'data' => $score,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not update score',
                'errors' => $score->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Delete method — remove a score
     *
     * DELETE /api/scores/:id.json
     */
    public function delete(?string $id = null): void
    {
        $this->request->allowMethod(['delete']);
        $user = $this->requireAuthentication();

        $score = $this->Scores->get($id, contain: [
            'Rounds' => ['Games'],
        ]);

        if ($score->round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to delete this score',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $gamePlayerId = $score->game_player_id;

        if ($this->Scores->delete($score)) {
            // Recalculate game player total
            $this->recalculateTotal($gamePlayerId);

            $this->set([
                'success' => true,
                'message' => 'Score deleted',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not delete score',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    /**
     * Add a single score and verify ownership
     */
    private function addSingle(\App\Model\Entity\User $user, array $data): void
    {
        if (empty($data['round_id'])) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'round_id is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Verify ownership through round -> game -> user
        $roundsTable = $this->getTableLocator()->get('Rounds');
        $round = $roundsTable->get($data['round_id'], contain: ['Games']);
        if ($round->game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to add scores to this round',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $score = $this->Scores->newEmptyEntity();
        $score = $this->Scores->patchEntity($score, $data);

        if ($this->Scores->save($score)) {
            // Recalculate game player total
            $this->recalculateTotal($score->game_player_id);

            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'data' => $score,
            ]);
        } else {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not save score',
                'errors' => $score->getErrors(),
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Add multiple scores in bulk
     */
    private function addBulk(\App\Model\Entity\User $user, array $scoresData): void
    {
        if (empty($scoresData)) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'No scores provided',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Verify ownership for all rounds referenced
        $roundIds = array_unique(array_column($scoresData, 'round_id'));
        $roundsTable = $this->getTableLocator()->get('Rounds');
        foreach ($roundIds as $roundId) {
            $round = $roundsTable->get($roundId, contain: ['Games']);
            if ($round->game->user_id !== $user->id) {
                $this->response = $this->response->withStatus(403);
                $this->set([
                    'success' => false,
                    'message' => 'Not authorized to add scores to this round',
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);

                return;
            }
        }

        $scores = [];
        $errors = [];
        $gamePlayerIds = [];

        foreach ($scoresData as $index => $scoreData) {
            $score = $this->Scores->newEmptyEntity();
            $score = $this->Scores->patchEntity($score, $scoreData);

            if ($this->Scores->save($score)) {
                $scores[] = $score;
                $gamePlayerIds[] = $score->game_player_id;
            } else {
                $errors[$index] = $score->getErrors();
            }
        }

        // Recalculate totals for all affected game players
        foreach (array_unique($gamePlayerIds) as $gamePlayerId) {
            $this->recalculateTotal($gamePlayerId);
        }

        if (!empty($errors)) {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'data' => $scores,
                'message' => 'Some scores could not be saved',
                'errors' => $errors,
            ]);
        } else {
            $this->response = $this->response->withStatus(201);
            $this->set([
                'success' => true,
                'data' => $scores,
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'message', 'errors']);
    }

    /**
     * Recalculate total_score for a game player based on all their scores
     */
    private function recalculateTotal(int $gamePlayerId): void
    {
        $gamePlayersTable = $this->getTableLocator()->get('GamePlayers');
        $gamePlayer = $gamePlayersTable->get($gamePlayerId);

        $total = $this->Scores->find()
            ->where(['game_player_id' => $gamePlayerId])
            ->select(['total' => $this->Scores->find()->func()->sum('points')])
            ->first();

        $gamePlayer->total_score = $total->total ?? 0;
        $gamePlayersTable->save($gamePlayer);
    }
}
