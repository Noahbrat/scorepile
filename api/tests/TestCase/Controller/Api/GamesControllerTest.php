<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;

class GamesControllerTest extends ApiTestCase
{
    public function testIndexReturnsOnlyOwnGames(): void
    {
        $this->createGame($this->userId, 'My Game');
        $this->createGame($this->otherUserId, 'Other Game');

        $this->setAuth();
        $this->get('/api/games.json');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertEquals('My Game', $body['data'][0]['name']);
    }

    public function testAddCreatesGameWithPlayers(): void
    {
        $player1 = $this->createPlayer($this->userId, 'Alice');
        $player2 = $this->createPlayer($this->userId, 'Bob');

        $this->setAuth();
        $this->post('/api/games.json', json_encode([
            'name' => 'New Game',
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('New Game', $body['data']['name']);

        // Add players to the game via GamePlayers
        $gameId = $body['data']['id'];
        $this->addPlayerToGame($gameId, $player1->id);
        $this->addPlayerToGame($gameId, $player2->id);

        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $count = $gpTable->find()->where(['game_id' => $gameId])->count();
        $this->assertEquals(2, $count);
    }

    public function testAddCreatesGameWithGameType(): void
    {
        $gameType = $this->createGameType($this->userId);

        $this->setAuth();
        $this->post('/api/games.json', json_encode([
            'name' => 'Typed Game',
            'game_type_id' => $gameType->id,
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals($gameType->id, $body['data']['game_type_id']);
    }

    public function testViewIncludesPlayersRoundsScores(): void
    {
        $game = $this->createGame($this->userId);
        $player = $this->createPlayer($this->userId, 'Alice');
        $gp = $this->addPlayerToGame($game->id, $player->id);
        $round = $this->createRound($game->id, 1);

        $scoresTable = TableRegistry::getTableLocator()->get('Scores');
        $scoresTable->saveOrFail($scoresTable->newEntity([
            'round_id' => $round->id,
            'game_player_id' => $gp->id,
            'points' => 50,
        ]));

        $this->setAuth();
        $this->get("/api/games/{$game->id}.json");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('game_players', $body['data']);
        $this->assertArrayHasKey('rounds', $body['data']);
        $this->assertNotEmpty($body['data']['game_players']);
        $this->assertNotEmpty($body['data']['rounds']);
    }

    public function testCompleteCalculatesRanksHighWins(): void
    {
        $gameType = $this->createGameType($this->userId, 'high_wins');
        $game = $this->createGame($this->userId, 'High Wins Game', $gameType->id);

        $player1 = $this->createPlayer($this->userId, 'High Scorer');
        $player2 = $this->createPlayer($this->userId, 'Low Scorer');

        $gp1 = $this->addPlayerToGame($game->id, $player1->id, 100);
        $gp2 = $this->addPlayerToGame($game->id, $player2->id, 50);

        $this->setAuth();
        $this->post("/api/games/{$game->id}/complete");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('completed', $body['data']['status']);

        // Verify ranks — highest score should be rank 1
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $winner = $gpTable->get($gp1->id);
        $loser = $gpTable->get($gp2->id);

        $this->assertEquals(1, $winner->final_rank);
        $this->assertTrue($winner->is_winner);
        $this->assertEquals(2, $loser->final_rank);
        $this->assertFalse($loser->is_winner);
    }

    public function testCompleteCalculatesRanksLowWins(): void
    {
        $gameType = $this->createGameType($this->userId, 'low_wins');
        $game = $this->createGame($this->userId, 'Low Wins Game', $gameType->id);

        $player1 = $this->createPlayer($this->userId, 'Low Scorer');
        $player2 = $this->createPlayer($this->userId, 'High Scorer');

        $gp1 = $this->addPlayerToGame($game->id, $player1->id, 30);
        $gp2 = $this->addPlayerToGame($game->id, $player2->id, 100);

        $this->setAuth();
        $this->post("/api/games/{$game->id}/complete");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);

        // Verify ranks — lowest score should be rank 1
        $gpTable = TableRegistry::getTableLocator()->get('GamePlayers');
        $winner = $gpTable->get($gp1->id);
        $loser = $gpTable->get($gp2->id);

        $this->assertEquals(1, $winner->final_rank);
        $this->assertTrue($winner->is_winner);
        $this->assertEquals(2, $loser->final_rank);
        $this->assertFalse($loser->is_winner);
    }

    public function testCompleteSetsWinnerAndCompletedAt(): void
    {
        $game = $this->createGame($this->userId, 'Complete Me');
        $player = $this->createPlayer($this->userId, 'Solo');
        $this->addPlayerToGame($game->id, $player->id, 50);

        $this->setAuth();
        $this->post("/api/games/{$game->id}/complete");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('completed', $body['data']['status']);
        $this->assertNotNull($body['data']['completed_at']);
    }

    public function testDeleteCascadesToRoundsAndScores(): void
    {
        $game = $this->createGame($this->userId);
        $player = $this->createPlayer($this->userId, 'Alice');
        $gp = $this->addPlayerToGame($game->id, $player->id);
        $round = $this->createRound($game->id, 1);

        $scoresTable = TableRegistry::getTableLocator()->get('Scores');
        $score = $scoresTable->saveOrFail($scoresTable->newEntity([
            'round_id' => $round->id,
            'game_player_id' => $gp->id,
            'points' => 25,
        ]));

        $this->setAuth();
        $this->delete("/api/games/{$game->id}.json");

        $this->assertResponseOk();

        $gamesTable = TableRegistry::getTableLocator()->get('Games');
        $roundsTable = TableRegistry::getTableLocator()->get('Rounds');
        $this->assertFalse($gamesTable->exists(['id' => $game->id]));
        $this->assertFalse($roundsTable->exists(['id' => $round->id]));
        $this->assertFalse($scoresTable->exists(['id' => $score->id]));
    }
}
