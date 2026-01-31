<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;

class PlayersControllerTest extends ApiTestCase
{
    public function testIndexRequiresAuth(): void
    {
        $this->get('/api/players.json');

        $this->assertResponseCode(401);
    }

    public function testIndexReturnsOnlyOwnPlayers(): void
    {
        $playersTable = TableRegistry::getTableLocator()->get('Players');

        $playersTable->saveOrFail($playersTable->newEntity([
            'user_id' => $this->userId,
            'name' => 'My Player',
        ]));
        $playersTable->saveOrFail($playersTable->newEntity([
            'user_id' => $this->otherUserId,
            'name' => 'Other Player',
        ]));

        $this->setAuth();
        $this->get('/api/players.json');

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertEquals('My Player', $body['data'][0]['name']);
    }

    public function testAddCreatesPlayer(): void
    {
        $this->setAuth();
        $this->post('/api/players.json', json_encode([
            'name' => 'New Player',
            'color' => '#FF0000',
        ]));

        $this->assertResponseCode(201);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('New Player', $body['data']['name']);
        $this->assertEquals('#FF0000', $body['data']['color']);
        $this->assertEquals($this->userId, $body['data']['user_id']);
    }

    public function testAddValidatesRequiredFields(): void
    {
        $this->setAuth();
        $this->post('/api/players.json', json_encode([]));

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('name', $body['errors']);
    }

    public function testAddEnforcesUniqueNamePerUser(): void
    {
        $this->createPlayer($this->userId, 'Duplicate');

        $this->setAuth();
        $this->post('/api/players.json', json_encode([
            'name' => 'Duplicate',
        ]));

        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
    }

    public function testEditUpdatesPlayer(): void
    {
        $player = $this->createPlayer($this->userId, 'Original');

        $this->setAuth();
        $this->put("/api/players/{$player->id}.json", json_encode([
            'name' => 'Updated',
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('Updated', $body['data']['name']);
    }

    public function testEditCannotUpdateOtherUsersPlayer(): void
    {
        $player = $this->createPlayer($this->otherUserId, 'Other Player');

        $this->setAuth();
        $this->put("/api/players/{$player->id}.json", json_encode([
            'name' => 'Hacked',
        ]));

        $this->assertResponseCode(403);
    }

    public function testDeleteRemovesPlayer(): void
    {
        $player = $this->createPlayer($this->userId, 'Delete Me');

        $this->setAuth();
        $this->delete("/api/players/{$player->id}.json");

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);

        $playersTable = TableRegistry::getTableLocator()->get('Players');
        $this->assertFalse($playersTable->exists(['id' => $player->id]));
    }

    public function testDeleteCannotRemoveOtherUsersPlayer(): void
    {
        $player = $this->createPlayer($this->otherUserId, 'Other Player');

        $this->setAuth();
        $this->delete("/api/players/{$player->id}.json");

        $this->assertResponseCode(403);

        $playersTable = TableRegistry::getTableLocator()->get('Players');
        $this->assertTrue($playersTable->exists(['id' => $player->id]));
    }
}
