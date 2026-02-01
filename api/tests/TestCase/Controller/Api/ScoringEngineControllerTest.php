<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;

/**
 * Integration tests for the scoring engine API endpoints:
 * - POST /api/games/:id/calculate-round.json
 * - POST /api/games/:id/save-round.json
 *
 * Also tests system game types visibility and protection.
 */
class ScoringEngineControllerTest extends ApiTestCase
{
    private array $fiveHundredConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiveHundredConfig = [
            'engine' => 'five_hundred',
            'scoring_direction' => 'high_wins',
            'teams' => ['enabled' => true, 'size' => 2],
            'target_score' => 500,
            'lose_score' => -500,
            'bid_table' => [
                '6_spades' => 40, '6_clubs' => 60, '6_diamonds' => 80, '6_hearts' => 100, '6_no_trump' => 120,
                '7_spades' => 140, '7_clubs' => 160, '7_diamonds' => 180, '7_hearts' => 200, '7_no_trump' => 220,
                '8_spades' => 240, '8_clubs' => 260, '8_diamonds' => 280, '8_hearts' => 300, '8_no_trump' => 320,
                '9_spades' => 340, '9_clubs' => 360, '9_diamonds' => 380, '9_hearts' => 400, '9_no_trump' => 420,
                '10_spades' => 440, '10_clubs' => 460, '10_diamonds' => 480, '10_hearts' => 500, '10_no_trump' => 520,
                'misere' => 250,
                'open_misere' => 500,
            ],
            'misere_enabled' => true,
            'open_misere_enabled' => true,
            'scoring_rules' => [
                'bid_won' => 'bid_value',
                'bid_lost' => '-bid_value',
                'opponent_per_trick' => 10,
            ],
        ];
    }

    /**
     * Helper: set up a full 500 game context with 4 players on 2 teams
     */
    private function setupFiveHundredGame(): array
    {
        $gameType = $this->createGameType(
            $this->userId,
            'high_wins',
            '500 Test',
            $this->fiveHundredConfig,
        );

        $game = $this->createGame($this->userId, 'My 500 Game', $gameType->id);

        $p1 = $this->createPlayer($this->userId, 'Alice');
        $p2 = $this->createPlayer($this->userId, 'Bob');
        $p3 = $this->createPlayer($this->userId, 'Carol');
        $p4 = $this->createPlayer($this->userId, 'Dave');

        $gp1 = $this->addPlayerToGame($game->id, $p1->id, 0, 1);
        $gp2 = $this->addPlayerToGame($game->id, $p2->id, 0, 1);
        $gp3 = $this->addPlayerToGame($game->id, $p3->id, 0, 2);
        $gp4 = $this->addPlayerToGame($game->id, $p4->id, 0, 2);

        return [
            'game' => $game,
            'gameType' => $gameType,
            'gamePlayers' => [$gp1, $gp2, $gp3, $gp4],
            'players' => [$p1, $p2, $p3, $p4],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // calculate-round endpoint
    // ══════════════════════════════════════════════════════════════

    public function testCalculateRoundReturnsPreview(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/calculate-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '7_hearts',
                'bid_tricks' => 7,
                'tricks_won' => ['team_1' => 8, 'team_2' => 2],
            ],
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['bid_made']);
        $this->assertEquals(200, $body['data']['bid_value']);
        $this->assertEquals(200, $body['data']['scores']['team_1']);
        $this->assertEquals(20, $body['data']['scores']['team_2']);
    }

    public function testCalculateRoundFailedBid(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/calculate-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '8_clubs',
                'bid_tricks' => 8,
                'tricks_won' => ['team_1' => 6, 'team_2' => 4],
            ],
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['data']['bid_made']);
        $this->assertEquals(-260, $body['data']['scores']['team_1']);
        $this->assertEquals(40, $body['data']['scores']['team_2']);
    }

    public function testCalculateRoundValidationErrors(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/calculate-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '7_hearts',
                'tricks_won' => ['team_1' => 5, 'team_2' => 6], // 11 != 10
            ],
        ]));

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('errors', $body);
    }

    public function testCalculateRoundRequiresAuth(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setJsonHeaders();
        $this->post("/api/games/{$ctx['game']->id}/calculate-round.json", json_encode([
            'round_data' => [],
        ]));

        $this->assertResponseCode(401);
    }

    public function testCalculateRoundForbiddenForOtherUser(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth($this->otherAccessToken);
        $this->post("/api/games/{$ctx['game']->id}/calculate-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '7_hearts',
                'bid_tricks' => 7,
                'tricks_won' => ['team_1' => 7, 'team_2' => 3],
            ],
        ]));

        $this->assertResponseCode(403);
    }

    public function testCalculateRoundRequiresScoringEngine(): void
    {
        // Create game WITHOUT scoring config
        $gameType = $this->createGameType($this->userId, 'high_wins', 'Simple');
        $game = $this->createGame($this->userId, 'Simple Game', $gameType->id);

        $this->setAuth();
        $this->post("/api/games/{$game->id}/calculate-round.json", json_encode([
            'round_data' => [],
        ]));

        $this->assertResponseCode(400);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('scoring engine', $body['message']);
    }

    // ══════════════════════════════════════════════════════════════
    // save-round endpoint
    // ══════════════════════════════════════════════════════════════

    public function testSaveRoundCreatesRoundAndScores(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '7_hearts',
                'bid_tricks' => 7,
                'tricks_won' => ['team_1' => 7, 'team_2' => 3],
            ],
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals(1, $body['data']['round_number']);
        $this->assertNotEmpty($body['data']['scores']);

        // Verify round was persisted
        $roundsTable = TableRegistry::getTableLocator()->get('Rounds');
        $round = $roundsTable->get($body['data']['id']);
        $this->assertNotNull($round);
        $this->assertNotNull($round->round_data);

        // Verify scores were saved for all 4 game players
        $scoresTable = TableRegistry::getTableLocator()->get('Scores');
        $scoreCount = $scoresTable->find()->where(['round_id' => $round->id])->count();
        $this->assertEquals(4, $scoreCount);
    }

    public function testSaveRoundMapsTeamScoresToPlayers(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '6_clubs',
                'bid_tricks' => 6,
                'tricks_won' => ['team_1' => 6, 'team_2' => 4],
            ],
        ]));

        $this->assertResponseCode(201);

        // Team 1 players (Alice, Bob) should have 60 each (bid value for 6 clubs)
        // Team 2 players (Carol, Dave) should have 40 each (4 tricks * 10)
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');

        $team1Gp1 = $gpTable->get($ctx['gamePlayers'][0]->id);
        $team1Gp2 = $gpTable->get($ctx['gamePlayers'][1]->id);
        $team2Gp1 = $gpTable->get($ctx['gamePlayers'][2]->id);
        $team2Gp2 = $gpTable->get($ctx['gamePlayers'][3]->id);

        $this->assertEquals(60, (float)$team1Gp1->total_score);
        $this->assertEquals(60, (float)$team1Gp2->total_score);
        $this->assertEquals(40, (float)$team2Gp1->total_score);
        $this->assertEquals(40, (float)$team2Gp2->total_score);
    }

    public function testSaveRoundAutoIncrementsRoundNumber(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $validRound = [
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '6_spades',
                'bid_tricks' => 6,
                'tricks_won' => ['team_1' => 6, 'team_2' => 4],
            ],
        ];

        // Round 1
        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode($validRound));
        $this->assertResponseCode(201);
        $body1 = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body1['data']['round_number']);

        // Round 2
        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode($validRound));
        $this->assertResponseCode(201);
        $body2 = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(2, $body2['data']['round_number']);
    }

    public function testSaveRoundAccumulatesTotals(): void
    {
        $ctx = $this->setupFiveHundredGame();

        // Round 1: team_1 bids 6 spades (40) and makes it
        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '6_spades',
                'bid_tricks' => 6,
                'tricks_won' => ['team_1' => 6, 'team_2' => 4],
            ],
        ]));
        $this->assertResponseCode(201);

        // Round 2: team_2 bids 7 hearts (200) and makes it
        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_2',
                'bid_key' => '7_hearts',
                'bid_tricks' => 7,
                'tricks_won' => ['team_1' => 3, 'team_2' => 7],
            ],
        ]));
        $this->assertResponseCode(201);

        // Team 1: round1=40, round2=30 (3 tricks * 10) = 70
        // Team 2: round1=40 (4 tricks * 10), round2=200 = 240
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');

        $team1Gp = $gpTable->get($ctx['gamePlayers'][0]->id);
        $team2Gp = $gpTable->get($ctx['gamePlayers'][2]->id);

        $this->assertEquals(70, (float)$team1Gp->total_score);
        $this->assertEquals(240, (float)$team2Gp->total_score);
    }

    public function testSaveRoundWithDealer(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $dealerGpId = $ctx['gamePlayers'][0]->id;

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '6_spades',
                'bid_tricks' => 6,
                'tricks_won' => ['team_1' => 6, 'team_2' => 4],
            ],
            'dealer_game_player_id' => $dealerGpId,
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);

        // Verify the dealer was stored
        $roundsTable = TableRegistry::getTableLocator()->get('Rounds');
        $round = $roundsTable->get($body['data']['id']);
        $this->assertEquals($dealerGpId, $round->dealer_game_player_id);
    }

    public function testSaveRoundValidationErrors(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                // Missing bid_key and tricks_won
            ],
        ]));

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
    }

    public function testSaveRoundRequiresAuth(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setJsonHeaders();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [],
        ]));

        $this->assertResponseCode(401);
    }

    public function testSaveRoundForbiddenForOtherUser(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth($this->otherAccessToken);
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => '6_spades',
                'bid_tricks' => 6,
                'tricks_won' => ['team_1' => 6, 'team_2' => 4],
            ],
        ]));

        $this->assertResponseCode(403);
    }

    public function testSaveRoundMisereScoring(): void
    {
        $ctx = $this->setupFiveHundredGame();

        $this->setAuth();
        $this->post("/api/games/{$ctx['game']->id}/save-round.json", json_encode([
            'round_data' => [
                'bidder_team' => 'team_1',
                'bid_key' => 'misere',
                'tricks_won' => ['team_1' => 0, 'team_2' => 10],
            ],
        ]));

        $this->assertResponseCode(201);

        // Team 1 should get +250 (misère success), team 2 should get 0
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');

        $team1Gp = $gpTable->get($ctx['gamePlayers'][0]->id);
        $team2Gp = $gpTable->get($ctx['gamePlayers'][2]->id);

        $this->assertEquals(250, (float)$team1Gp->total_score);
        $this->assertEquals(0, (float)$team2Gp->total_score);
    }

    // ══════════════════════════════════════════════════════════════
    // System game types
    // ══════════════════════════════════════════════════════════════

    public function testIndexIncludesSystemGameTypes(): void
    {
        $this->createGameType($this->userId, 'high_wins', 'My Type');
        $this->createGameType($this->userId, 'high_wins', '500', $this->fiveHundredConfig, true);

        $this->setAuth();
        $this->get('/api/game-types.json');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);

        $names = array_column($body['data'], 'name');
        $this->assertContains('My Type', $names);
        $this->assertContains('500', $names);
    }

    public function testSystemGameTypesVisibleToAllUsers(): void
    {
        $this->createGameType($this->userId, 'high_wins', '500 System', $this->fiveHundredConfig, true);

        // Other user should see it too
        $this->setAuth($this->otherAccessToken);
        $this->get('/api/game-types.json');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertEquals('500 System', $body['data'][0]['name']);
    }

    public function testCanViewSystemGameType(): void
    {
        $systemType = $this->createGameType($this->userId, 'high_wins', '500 System', $this->fiveHundredConfig, true);

        // Other user should be able to view it
        $this->setAuth($this->otherAccessToken);
        $this->get("/api/game-types/{$systemType->id}.json");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('500 System', $body['data']['name']);
    }

    public function testCannotDeleteSystemGameType(): void
    {
        $systemType = $this->createGameType($this->userId, 'high_wins', '500 System', $this->fiveHundredConfig, true);

        $this->setAuth();
        $this->delete("/api/game-types/{$systemType->id}.json");

        $this->assertResponseCode(403);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('System game types', $body['message']);

        // Verify it still exists
        $table = TableRegistry::getTableLocator()->get('GameTypes');
        $this->assertTrue($table->exists(['id' => $systemType->id]));
    }

    // ══════════════════════════════════════════════════════════════
    // Game creation with teams
    // ══════════════════════════════════════════════════════════════

    public function testCreateGameWithTeamAssignments(): void
    {
        $gameType = $this->createGameType(
            $this->userId,
            'high_wins',
            '500 Test',
            $this->fiveHundredConfig,
        );

        $p1 = $this->createPlayer($this->userId, 'Alice');
        $p2 = $this->createPlayer($this->userId, 'Bob');
        $p3 = $this->createPlayer($this->userId, 'Carol');
        $p4 = $this->createPlayer($this->userId, 'Dave');

        $this->setAuth();
        $this->post('/api/games.json', json_encode([
            'name' => 'Team Game',
            'game_type_id' => $gameType->id,
            'player_ids' => [$p1->id, $p2->id, $p3->id, $p4->id],
            'team_assignments' => [
                $p1->id => 1,
                $p2->id => 1,
                $p3->id => 2,
                $p4->id => 2,
            ],
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);

        // Verify team assignments
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $gamePlayers = $gpTable->find()
            ->where(['game_id' => $body['data']['id']])
            ->orderBy(['player_id' => 'ASC'])
            ->all()
            ->toArray();

        $this->assertCount(4, $gamePlayers);

        // Map by player_id
        $byPlayer = [];
        foreach ($gamePlayers as $gp) {
            $byPlayer[$gp->player_id] = $gp->team;
        }

        $this->assertEquals(1, $byPlayer[$p1->id]);
        $this->assertEquals(1, $byPlayer[$p2->id]);
        $this->assertEquals(2, $byPlayer[$p3->id]);
        $this->assertEquals(2, $byPlayer[$p4->id]);
    }
}
