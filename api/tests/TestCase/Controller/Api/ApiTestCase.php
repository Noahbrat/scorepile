<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Firebase\JWT\JWT;

abstract class ApiTestCase extends TestCase
{
    use IntegrationTestTrait;

    protected string $userId;
    protected string $otherUserId;
    protected string $accessToken;
    protected string $otherAccessToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Route all 'default' connection queries to the 'test' database
        ConnectionManager::alias('test', 'default');

        // Clear the table registry to pick up the aliased connection
        TableRegistry::getTableLocator()->clear();

        $this->cleanDatabase();

        $this->createTestUsers();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        TableRegistry::getTableLocator()->clear();
        parent::tearDown();
    }

    /**
     * Set JSON request headers without auth. Used for unauthenticated requests.
     */
    protected function setJsonHeaders(): void
    {
        $this->_request = [];
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function cleanDatabase(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute('SET FOREIGN_KEY_CHECKS = 0');

        $tables = ['scores', 'rounds', 'game_players', 'games', 'game_types', 'players', 'users'];
        foreach ($tables as $table) {
            $connection->execute("TRUNCATE TABLE `{$table}`");
        }

        $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createTestUsers(): void
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users');

        $user = $usersTable->newEntity([
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => 'Password1',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
        $usersTable->saveOrFail($user);
        $this->userId = $user->id;
        $this->accessToken = $this->generateToken($user->id);

        $other = $usersTable->newEntity([
            'email' => 'other@example.com',
            'username' => 'otheruser',
            'password' => 'Password1',
            'first_name' => 'Other',
            'last_name' => 'User',
        ]);
        $usersTable->saveOrFail($other);
        $this->otherUserId = $other->id;
        $this->otherAccessToken = $this->generateToken($other->id);
    }

    protected function generateToken(string $userId): string
    {
        $secret = Configure::read('Security.jwtSecret');

        return JWT::encode([
            'sub' => $userId,
            'exp' => time() + 600,
            'type' => 'access',
        ], $secret, 'HS256');
    }

    protected function setAuth(?string $token = null): void
    {
        $token = $token ?? $this->accessToken;
        $this->_request = [];
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);
    }

    protected function createGame(string $userId, string $name = 'Test Game', ?int $gameTypeId = null, string $status = 'active', ?array $gameConfig = null): \Cake\Datasource\EntityInterface
    {
        $table = TableRegistry::getTableLocator()->get('Games');
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'status' => $status,
        ];
        if ($gameTypeId) {
            $data['game_type_id'] = $gameTypeId;
        }
        if ($gameConfig !== null) {
            $data['game_config'] = $gameConfig;
        }

        return $table->saveOrFail($table->newEntity($data));
    }

    protected function createGameType(string $userId, string $direction = 'high_wins', ?string $name = null, ?array $scoringConfig = null, bool $isSystem = false): \Cake\Datasource\EntityInterface
    {
        $table = TableRegistry::getTableLocator()->get('GameTypes');

        $data = [
            'name' => $name ?? 'Type ' . uniqid(),
            'scoring_direction' => $direction,
            'is_system' => $isSystem,
        ];

        if (!$isSystem) {
            $data['user_id'] = $userId;
        }

        if ($scoringConfig !== null) {
            $data['scoring_config'] = $scoringConfig;
        }

        return $table->saveOrFail($table->newEntity($data));
    }

    protected function createPlayer(string $userId, string $name): \Cake\Datasource\EntityInterface
    {
        $table = TableRegistry::getTableLocator()->get('Players');

        return $table->saveOrFail($table->newEntity([
            'user_id' => $userId,
            'name' => $name,
        ]));
    }

    protected function addPlayerToGame(int $gameId, int $playerId, float $totalScore = 0, ?int $team = null): \Cake\Datasource\EntityInterface
    {
        $table = TableRegistry::getTableLocator()->get('GamePlayers');

        $data = [
            'game_id' => $gameId,
            'player_id' => $playerId,
            'total_score' => $totalScore,
        ];

        if ($team !== null) {
            $data['team'] = $team;
        }

        return $table->saveOrFail($table->newEntity($data));
    }

    protected function createRound(int $gameId, int $roundNumber = 1): \Cake\Datasource\EntityInterface
    {
        $table = TableRegistry::getTableLocator()->get('Rounds');

        return $table->saveOrFail($table->newEntity([
            'game_id' => $gameId,
            'round_number' => $roundNumber,
        ]));
    }
}
