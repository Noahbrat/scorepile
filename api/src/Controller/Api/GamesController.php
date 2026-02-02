<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\JwtAuthTrait;
use App\Controller\Trait\CrudErrorSerializationTrait;
use App\Service\ScoringEngine\ScoringEngineFactory;
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

        // Extract player_ids and team_assignments before patching (not direct entity fields)
        $playerIds = $data['player_ids'] ?? [];
        $teamAssignments = $data['team_assignments'] ?? [];
        unset($data['player_ids'], $data['team_assignments']);

        $game = $this->Games->patchEntity($game, $data);

        if ($this->Games->save($game)) {
            // Create game_players for each selected player
            if (!empty($playerIds)) {
                $gamePlayersTable = $this->getTableLocator()->get('GamePlayers');
                $playersTable = $this->getTableLocator()->get('Players');

                foreach ($playerIds as $playerId) {
                    // Verify player belongs to this user
                    try {
                        $player = $playersTable->get($playerId);
                        if ($player->user_id !== $user->id) {
                            continue;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }

                    $gpData = [
                        'game_id' => $game->id,
                        'player_id' => (int)$playerId,
                        'total_score' => 0,
                    ];
                    // Assign team if provided
                    if (isset($teamAssignments[$playerId])) {
                        $gpData['team'] = (int)$teamAssignments[$playerId];
                    }
                    $gamePlayer = $gamePlayersTable->newEntity($gpData);
                    $gamePlayersTable->save($gamePlayer);
                }

                // Reload game with players for response
                $game = $this->Games->get($game->id, contain: ['GamePlayers' => ['Players'], 'GameTypes']);
            }

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
     * Assign teams — update team assignments for game players
     *
     * POST /api/games/:id/assign-teams
     * Body: { "teams": { "player_id": team_number, ... } }
     */
    public function assignTeams(?string $id = null): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id, contain: ['GamePlayers.Players']);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized to edit this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($game->status !== 'active') {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Can only assign teams on active games',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $teams = $this->request->getData('teams');

        if (empty($teams) || !is_array($teams)) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'teams is required as an object mapping player_id to team number',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $gamePlayersTable = $this->fetchTable('GamePlayers');

        foreach ($game->game_players as $gp) {
            $playerId = (string) $gp->player_id;
            if (isset($teams[$playerId])) {
                $gp->team = (int) $teams[$playerId];
                $gamePlayersTable->save($gp);
            }
        }

        // Reload with fresh data
        $game = $this->Games->get($id, contain: [
            'GameTypes',
            'GamePlayers' => ['sort' => ['GamePlayers.team' => 'ASC', 'GamePlayers.id' => 'ASC']],
            'GamePlayers.Players',
        ]);

        $this->set([
            'success' => true,
            'data' => $game,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
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

    /**
     * Calculate round scores without saving — for preview
     *
     * POST /api/games/:id/calculate-round.json
     */
    public function calculateRound(?string $id = null): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id, contain: ['GameTypes']);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $scoringConfig = $game->game_type?->scoring_config;
        if (!$scoringConfig) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'This game type does not have a scoring engine',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $roundData = $this->request->getData('round_data') ?? [];
        $gameConfig = array_merge($scoringConfig, $game->game_config ?? []);

        $engine = ScoringEngineFactory::forGameType($scoringConfig);

        // Validate
        $validation = $engine->validateRoundData($roundData, $gameConfig);
        if ($validation !== true) {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Invalid round data',
                'errors' => $validation,
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

            return;
        }

        // Calculate
        $result = $engine->calculateRoundScores($roundData, $gameConfig);

        $this->set([
            'success' => true,
            'data' => $result,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Save a round with engine-calculated scores in one transaction.
     *
     * When round_data has bid info but NO tricks_won, creates a round
     * with status='playing' (bid-only). When both bid and tricks are
     * present, creates with status='completed' (full save, backward compat).
     *
     * POST /api/games/:id/save-round.json
     */
    public function saveRound(?string $id = null): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id, contain: ['GameTypes', 'GamePlayers']);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Check for existing playing round — only one active round allowed
        $roundsTable = $this->getTableLocator()->get('Rounds');
        $existingPlaying = $roundsTable->find()
            ->where(['game_id' => $game->id, 'status' => 'playing'])
            ->first();

        if ($existingPlaying) {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'A round is already in progress. Complete or cancel it first.',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $roundData = $this->request->getData('round_data') ?? [];
        $dealerGamePlayerId = $this->request->getData('dealer_game_player_id');
        $scoringConfig = $game->game_type?->scoring_config;
        $gameConfig = array_merge($scoringConfig ?? [], $game->game_config ?? []);

        // Determine if this is a bid-only save (no tricks_won) or full save
        $hasTricksWon = !empty($roundData['tricks_won']);
        $isBidOnly = !empty($roundData['bid_key']) && !$hasTricksWon;

        // Calculate scores using engine if available and tricks are provided
        $calculatedScores = [];
        if ($scoringConfig && $hasTricksWon) {
            $engine = ScoringEngineFactory::forGameType($scoringConfig);

            $validation = $engine->validateRoundData($roundData, $gameConfig);
            if ($validation !== true) {
                $this->response = $this->response->withStatus(422);
                $this->set([
                    'success' => false,
                    'message' => 'Invalid round data',
                    'errors' => $validation,
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

                return;
            }

            $result = $engine->calculateRoundScores($roundData, $gameConfig);
            $calculatedScores = $result['scores'] ?? [];
        } elseif ($scoringConfig && $isBidOnly) {
            // Validate bid-only fields (bidder_team and bid_key required)
            $errors = [];
            if (empty($roundData['bidder_team'])) {
                $errors[] = 'Bidding team is required';
            }
            if (empty($roundData['bid_key'])) {
                $errors[] = 'Bid is required';
            }
            if (!empty($errors)) {
                $this->response = $this->response->withStatus(422);
                $this->set([
                    'success' => false,
                    'message' => 'Invalid round data',
                    'errors' => $errors,
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

                return;
            }
        } elseif ($scoringConfig && !$hasTricksWon && !$isBidOnly) {
            // Engine game but no valid bid or tricks — run full validation to get errors
            $engine = ScoringEngineFactory::forGameType($scoringConfig);
            $validation = $engine->validateRoundData($roundData, $gameConfig);
            if ($validation !== true) {
                $this->response = $this->response->withStatus(422);
                $this->set([
                    'success' => false,
                    'message' => 'Invalid round data',
                    'errors' => $validation,
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

                return;
            }
        }

        $scoresTable = $this->getTableLocator()->get('Scores');

        // Auto-assign round_number
        $maxRound = $roundsTable->find()
            ->where(['game_id' => $game->id])
            ->select(['max_round' => $roundsTable->find()->func()->max('round_number')])
            ->first();
        $roundNumber = (int)(($maxRound->max_round ?? 0) + 1);

        $roundStatus = $isBidOnly ? 'playing' : 'completed';

        $roundEntity = $roundsTable->newEntity([
            'game_id' => $game->id,
            'round_number' => $roundNumber,
            'round_data' => $roundData,
            'dealer_game_player_id' => $dealerGamePlayerId ? (int)$dealerGamePlayerId : null,
            'status' => $roundStatus,
        ]);

        if (!$roundsTable->save($roundEntity)) {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not save round',
                'errors' => $roundEntity->getErrors(),
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

            return;
        }

        // Only save scores for completed rounds
        if ($roundStatus === 'completed') {
            $this->saveRoundScores($game, $roundEntity, $calculatedScores, $scoringConfig);
        }

        // Reload round with scores
        $roundEntity = $roundsTable->get($roundEntity->id, contain: [
            'Scores' => ['GamePlayers' => ['Players']],
        ]);

        $this->response = $this->response->withStatus(201);
        $this->set([
            'success' => true,
            'data' => $roundEntity,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Complete a playing round — add tricks and calculate scores.
     *
     * POST /api/games/:id/rounds/:roundId/complete.json
     */
    public function completeRound(?string $id = null, ?string $roundId = null): void
    {
        $this->request->allowMethod(['post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id, contain: ['GameTypes', 'GamePlayers']);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $roundsTable = $this->getTableLocator()->get('Rounds');
        $roundEntity = $roundsTable->get((int)$roundId);

        // Validate round belongs to this game
        if ($roundEntity->game_id !== $game->id) {
            $this->response = $this->response->withStatus(404);
            $this->set([
                'success' => false,
                'message' => 'Round not found for this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Validate round is in 'playing' status
        if ($roundEntity->status !== 'playing') {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Round is not in playing status',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $tricksWon = $this->request->getData('tricks_won') ?? [];
        if (empty($tricksWon) || !is_array($tricksWon)) {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'tricks_won is required',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Merge tricks into existing round_data
        $roundData = $roundEntity->round_data ?? [];
        $roundData['tricks_won'] = $tricksWon;

        $scoringConfig = $game->game_type?->scoring_config;
        $gameConfig = array_merge($scoringConfig ?? [], $game->game_config ?? []);

        // Calculate scores
        $calculatedScores = [];
        if ($scoringConfig) {
            $engine = ScoringEngineFactory::forGameType($scoringConfig);

            $validation = $engine->validateRoundData($roundData, $gameConfig);
            if ($validation !== true) {
                $this->response = $this->response->withStatus(422);
                $this->set([
                    'success' => false,
                    'message' => 'Invalid round data',
                    'errors' => $validation,
                ]);
                $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

                return;
            }

            $result = $engine->calculateRoundScores($roundData, $gameConfig);
            $calculatedScores = $result['scores'] ?? [];
            $roundData['bid_made'] = $result['bid_made'] ?? null;
        }

        // Update round
        $roundEntity->round_data = $roundData;
        $roundEntity->status = 'completed';

        if (!$roundsTable->save($roundEntity)) {
            $this->response = $this->response->withStatus(422);
            $this->set([
                'success' => false,
                'message' => 'Could not update round',
                'errors' => $roundEntity->getErrors(),
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message', 'errors']);

            return;
        }

        // Save scores
        $this->saveRoundScores($game, $roundEntity, $calculatedScores, $scoringConfig);

        // Reload round with scores
        $roundEntity = $roundsTable->get($roundEntity->id, contain: [
            'Scores' => ['GamePlayers' => ['Players']],
        ]);

        $this->set([
            'success' => true,
            'data' => $roundEntity,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data']);
    }

    /**
     * Cancel a playing round — delete it.
     *
     * DELETE /api/games/:id/rounds/:roundId/cancel.json
     */
    public function cancelRound(?string $id = null, ?string $roundId = null): void
    {
        $this->request->allowMethod(['delete', 'post']);
        $user = $this->requireAuthentication();

        $game = $this->Games->get($id);

        if ($game->user_id !== $user->id) {
            $this->response = $this->response->withStatus(403);
            $this->set([
                'success' => false,
                'message' => 'Not authorized',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $roundsTable = $this->getTableLocator()->get('Rounds');
        $roundEntity = $roundsTable->get((int)$roundId);

        if ($roundEntity->game_id !== $game->id) {
            $this->response = $this->response->withStatus(404);
            $this->set([
                'success' => false,
                'message' => 'Round not found for this game',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($roundEntity->status !== 'playing') {
            $this->response = $this->response->withStatus(400);
            $this->set([
                'success' => false,
                'message' => 'Only playing rounds can be cancelled',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        if ($roundsTable->delete($roundEntity)) {
            $this->set([
                'success' => true,
                'message' => 'Round cancelled',
            ]);
        } else {
            $this->response = $this->response->withStatus(500);
            $this->set([
                'success' => false,
                'message' => 'Could not cancel round',
            ]);
        }
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    /**
     * Save calculated scores for a round, mapping team or player scores.
     */
    private function saveRoundScores($game, $roundEntity, array $calculatedScores, ?array $scoringConfig): void
    {
        $scoresTable = $this->getTableLocator()->get('Scores');
        $teamsEnabled = ($scoringConfig['teams']['enabled'] ?? false);

        if ($teamsEnabled && !empty($calculatedScores)) {
            foreach ($game->game_players as $gp) {
                $teamKey = 'team_' . $gp->team;
                if (isset($calculatedScores[$teamKey])) {
                    $score = $scoresTable->newEntity([
                        'round_id' => $roundEntity->id,
                        'game_player_id' => $gp->id,
                        'points' => (float)$calculatedScores[$teamKey],
                    ]);
                    $scoresTable->save($score);
                    $this->recalculateTotal($gp->id);
                }
            }
        } elseif (!empty($calculatedScores)) {
            foreach ($calculatedScores as $gamePlayerId => $points) {
                $score = $scoresTable->newEntity([
                    'round_id' => $roundEntity->id,
                    'game_player_id' => (int)$gamePlayerId,
                    'points' => (float)$points,
                ]);
                $scoresTable->save($score);
                $this->recalculateTotal((int)$gamePlayerId);
            }
        }
    }

    /**
     * Recalculate total_score for a game player based on all their scores
     */
    private function recalculateTotal(int $gamePlayerId): void
    {
        $gamePlayersTable = $this->getTableLocator()->get('GamePlayers');
        $scoresTable = $this->getTableLocator()->get('Scores');
        $gamePlayer = $gamePlayersTable->get($gamePlayerId);

        $total = $scoresTable->find()
            ->where(['game_player_id' => $gamePlayerId])
            ->select(['total' => $scoresTable->find()->func()->sum('points')])
            ->first();

        $gamePlayer->total_score = $total->total ?? 0;
        $gamePlayersTable->save($gamePlayer);
    }
}
