<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;

class ScoresControllerTest extends ApiTestCase
{
    /**
     * Set up a full game context: game, player, game_player, round
     */
    private function setupGameContext(): array
    {
        $game = $this->createGame($this->userId);
        $player = $this->createPlayer($this->userId, 'Alice');
        $gamePlayer = $this->addPlayerToGame($game->id, $player->id);
        $round = $this->createRound($game->id, 1);

        return compact('game', 'player', 'gamePlayer', 'round');
    }

    public function testAddScoreToRound(): void
    {
        $ctx = $this->setupGameContext();

        $this->setAuth();
        $this->post('/api/scores.json', json_encode([
            'round_id' => $ctx['round']->id,
            'game_player_id' => $ctx['gamePlayer']->id,
            'points' => 42,
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals(42, $body['data']['points']);
    }

    public function testAddScoreRecalculatesGamePlayerTotal(): void
    {
        $ctx = $this->setupGameContext();

        $this->setAuth();
        $this->post('/api/scores.json', json_encode([
            'round_id' => $ctx['round']->id,
            'game_player_id' => $ctx['gamePlayer']->id,
            'points' => 30,
        ]));

        $this->assertResponseCode(201);

        // Add second round with another score
        $round2 = $this->createRound($ctx['game']->id, 2);
        $this->setAuth();
        $this->post('/api/scores.json', json_encode([
            'round_id' => $round2->id,
            'game_player_id' => $ctx['gamePlayer']->id,
            'points' => 20,
        ]));

        $this->assertResponseCode(201);

        // Verify total is recalculated
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $gp = $gpTable->get($ctx['gamePlayer']->id);
        $this->assertEquals(50, (float)$gp->total_score);
    }

    public function testEditScoreRecalculatesTotal(): void
    {
        $ctx = $this->setupGameContext();

        $scoresTable = TableRegistry::getTableLocator()->get('Scores');
        $score = $scoresTable->saveOrFail($scoresTable->newEntity([
            'round_id' => $ctx['round']->id,
            'game_player_id' => $ctx['gamePlayer']->id,
            'points' => 50,
        ]));

        // Set initial total
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $gp = $gpTable->get($ctx['gamePlayer']->id);
        $gp->total_score = 50;
        $gpTable->saveOrFail($gp);

        $this->setAuth();
        $this->put("/api/scores/{$score->id}.json", json_encode([
            'points' => 75,
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);

        // Verify total is recalculated to new value
        $gp = $gpTable->get($ctx['gamePlayer']->id);
        $this->assertEquals(75, (float)$gp->total_score);
    }

    public function testBulkAddScoresForRound(): void
    {
        $game = $this->createGame($this->userId);
        $player1 = $this->createPlayer($this->userId, 'Bulk1');
        $player2 = $this->createPlayer($this->userId, 'Bulk2');
        $gp1 = $this->addPlayerToGame($game->id, $player1->id);
        $gp2 = $this->addPlayerToGame($game->id, $player2->id);
        $round = $this->createRound($game->id, 1);

        $this->setAuth();
        $this->post('/api/scores.json', json_encode([
            'scores' => [
                ['round_id' => $round->id, 'game_player_id' => $gp1->id, 'points' => 10],
                ['round_id' => $round->id, 'game_player_id' => $gp2->id, 'points' => 20],
            ],
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);

        // Verify totals recalculated
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $this->assertEquals(10, (float)$gpTable->get($gp1->id)->total_score);
        $this->assertEquals(20, (float)$gpTable->get($gp2->id)->total_score);
    }

    public function testValidatesGameOwnershipChain(): void
    {
        // Create a game owned by other user
        $otherGame = $this->createGame($this->otherUserId);
        $player = $this->createPlayer($this->otherUserId, 'Other Player');
        $gp = $this->addPlayerToGame($otherGame->id, $player->id);
        $round = $this->createRound($otherGame->id, 1);

        // Try to add score as our user -> should fail ownership check
        $this->setAuth();
        $this->post('/api/scores.json', json_encode([
            'round_id' => $round->id,
            'game_player_id' => $gp->id,
            'points' => 99,
        ]));

        $this->assertResponseCode(403);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
    }
}
