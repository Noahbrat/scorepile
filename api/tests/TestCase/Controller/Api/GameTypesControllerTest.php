<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;

class GameTypesControllerTest extends ApiTestCase
{
    public function testIndexReturnsOnlyOwnGameTypes(): void
    {
        $this->createGameType($this->userId, 'high_wins', 'My Game Type');
        $this->createGameType($this->otherUserId, 'low_wins', 'Other Game Type');

        $this->setAuth();
        $this->get('/api/game-types.json');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertEquals('My Game Type', $body['data'][0]['name']);
    }

    public function testAddCreatesGameType(): void
    {
        $this->setAuth();
        $this->post('/api/game-types.json', json_encode([
            'name' => 'Poker',
            'scoring_direction' => 'high_wins',
            'description' => 'Classic card game',
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('Poker', $body['data']['name']);
        $this->assertEquals('high_wins', $body['data']['scoring_direction']);
    }

    public function testAddValidatesScoringDirection(): void
    {
        $this->setAuth();
        $this->post('/api/game-types.json', json_encode([
            'name' => 'Bad Game',
            'scoring_direction' => 'invalid_value',
        ]));

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('scoring_direction', $body['errors']);
    }

    public function testAddEnforcesUniqueNamePerUser(): void
    {
        $this->createGameType($this->userId, 'high_wins', 'Duplicate');

        $this->setAuth();
        $this->post('/api/game-types.json', json_encode([
            'name' => 'Duplicate',
            'scoring_direction' => 'high_wins',
        ]));

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
    }

    public function testEditUpdatesGameType(): void
    {
        $gameType = $this->createGameType($this->userId, 'high_wins', 'Original');

        $this->setAuth();
        $this->put("/api/game-types/{$gameType->id}.json", json_encode([
            'name' => 'Updated',
            'scoring_direction' => 'low_wins',
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('Updated', $body['data']['name']);
        $this->assertEquals('low_wins', $body['data']['scoring_direction']);
    }

    public function testDeleteRemovesGameType(): void
    {
        $gameType = $this->createGameType($this->userId, 'high_wins', 'Delete Me');

        $this->setAuth();
        $this->delete("/api/game-types/{$gameType->id}.json");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);

        $table = TableRegistry::getTableLocator()->get('GameTypes');
        $this->assertFalse($table->exists(['id' => $gameType->id]));
    }
}
