<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\ScoringEngine;

use App\Service\ScoringEngine\FiveHundredEngine;
use Cake\TestSuite\TestCase;

class FiveHundredEngineTest extends TestCase
{
    private FiveHundredEngine $engine;
    private array $defaultConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FiveHundredEngine();
        $this->defaultConfig = $this->engine->getDefaultConfig();
    }

    // ── calculateRoundScores — Normal bids ────────────────────────

    public function testBidMadeExactTricks(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '7_hearts',
            'bid_tricks' => 7,
            'tricks_won' => ['team_1' => 7, 'team_2' => 3],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertTrue($result['bid_made']);
        $this->assertEquals(200, $result['bid_value']);
        $this->assertEquals(200, $result['scores']['team_1']);
        $this->assertEquals(30, $result['scores']['team_2']); // 3 * 10
    }

    public function testBidMadeMoreTricksThanBid(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '6_spades',
            'bid_tricks' => 6,
            'tricks_won' => ['team_1' => 9, 'team_2' => 1],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertTrue($result['bid_made']);
        $this->assertEquals(40, $result['bid_value']);
        // Bidder gets bid value even if they won more tricks
        $this->assertEquals(40, $result['scores']['team_1']);
        $this->assertEquals(10, $result['scores']['team_2']); // 1 * 10
    }

    public function testBidFailedFewerTricks(): void
    {
        $roundData = [
            'bidder_team' => 'team_2',
            'bid_key' => '8_diamonds',
            'bid_tricks' => 8,
            'tricks_won' => ['team_1' => 5, 'team_2' => 5],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertFalse($result['bid_made']);
        $this->assertEquals(280, $result['bid_value']);
        $this->assertEquals(-280, $result['scores']['team_2']); // negative bid value
        $this->assertEquals(50, $result['scores']['team_1']); // 5 * 10
    }

    public function testBidFailedZeroTricks(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '10_no_trump',
            'bid_tricks' => 10,
            'tricks_won' => ['team_1' => 0, 'team_2' => 10],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertFalse($result['bid_made']);
        $this->assertEquals(520, $result['bid_value']);
        $this->assertEquals(-520, $result['scores']['team_1']);
        $this->assertEquals(100, $result['scores']['team_2']); // 10 * 10
    }

    public function testHighValueBidTenHearts(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '10_hearts',
            'bid_tricks' => 10,
            'tricks_won' => ['team_1' => 10, 'team_2' => 0],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertTrue($result['bid_made']);
        $this->assertEquals(500, $result['bid_value']);
        $this->assertEquals(500, $result['scores']['team_1']);
        $this->assertEquals(0, $result['scores']['team_2']);
    }

    public function testOpponentScoringTenPerTrick(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '6_clubs',
            'bid_tricks' => 6,
            'tricks_won' => ['team_1' => 6, 'team_2' => 4],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertTrue($result['bid_made']);
        $this->assertEquals(60, $result['scores']['team_1']);
        $this->assertEquals(40, $result['scores']['team_2']); // 4 * 10
    }

    // ── calculateRoundScores — Misère ─────────────────────────────

    public function testMisereSuccess(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => 'misere',
            'tricks_won' => ['team_1' => 0, 'team_2' => 10],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertTrue($result['bid_made']);
        $this->assertEquals(250, $result['bid_value']);
        $this->assertEquals(250, $result['scores']['team_1']);
        $this->assertEquals(0, $result['scores']['team_2']); // No trick scoring on misère
    }

    public function testMisereFailure(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => 'misere',
            'tricks_won' => ['team_1' => 3, 'team_2' => 7],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertFalse($result['bid_made']);
        $this->assertEquals(250, $result['bid_value']);
        $this->assertEquals(-250, $result['scores']['team_1']);
        $this->assertEquals(0, $result['scores']['team_2']); // No trick scoring on misère
    }

    public function testOpenMisereSuccess(): void
    {
        $roundData = [
            'bidder_team' => 'team_2',
            'bid_key' => 'open_misere',
            'tricks_won' => ['team_1' => 10, 'team_2' => 0],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertTrue($result['bid_made']);
        $this->assertEquals(500, $result['bid_value']);
        $this->assertEquals(500, $result['scores']['team_2']);
        $this->assertEquals(0, $result['scores']['team_1']);
    }

    public function testOpenMisereFailure(): void
    {
        $roundData = [
            'bidder_team' => 'team_2',
            'bid_key' => 'open_misere',
            'tricks_won' => ['team_1' => 9, 'team_2' => 1],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertFalse($result['bid_made']);
        $this->assertEquals(500, $result['bid_value']);
        $this->assertEquals(-500, $result['scores']['team_2']);
        $this->assertEquals(0, $result['scores']['team_1']);
    }

    // ── calculateRoundScores — Edge cases ─────────────────────────

    public function testMissingBidKeyReturnsEmpty(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'tricks_won' => ['team_1' => 5, 'team_2' => 5],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertEmpty($result);
    }

    public function testMissingBidderTeamReturnsEmpty(): void
    {
        $roundData = [
            'bid_key' => '7_hearts',
            'tricks_won' => ['team_1' => 5, 'team_2' => 5],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertEmpty($result);
    }

    public function testInvalidBidKeyReturnsEmpty(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => 'invalid_bid',
            'tricks_won' => ['team_1' => 5, 'team_2' => 5],
        ];

        $result = $this->engine->calculateRoundScores($roundData, $this->defaultConfig);

        $this->assertEmpty($result);
    }

    // ── validateRoundData ─────────────────────────────────────────

    public function testValidateSucceeds(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '7_hearts',
            'tricks_won' => ['team_1' => 7, 'team_2' => 3],
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertTrue($result);
    }

    public function testValidateMissingBidderTeam(): void
    {
        $roundData = [
            'bid_key' => '7_hearts',
            'tricks_won' => ['team_1' => 7, 'team_2' => 3],
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertIsArray($result);
        $this->assertContains('Bidding team is required', $result);
    }

    public function testValidateMissingBidKey(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'tricks_won' => ['team_1' => 7, 'team_2' => 3],
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertIsArray($result);
        $this->assertContains('Bid is required', $result);
    }

    public function testValidateInvalidBidKey(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => 'invalid_bid',
            'tricks_won' => ['team_1' => 5, 'team_2' => 5],
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertIsArray($result);
        $this->assertContains('Invalid bid', $result);
    }

    public function testValidateTricksMustEqualTen(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '7_hearts',
            'tricks_won' => ['team_1' => 6, 'team_2' => 6],
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertIsArray($result);
        $this->assertContains('Total tricks must equal 10', $result);
    }

    public function testValidateMissingTricksWon(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '7_hearts',
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertIsArray($result);
        $this->assertContains('Tricks won is required', $result);
    }

    public function testValidateNegativeTricks(): void
    {
        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => '7_hearts',
            'tricks_won' => ['team_1' => -1, 'team_2' => 11],
        ];

        $result = $this->engine->validateRoundData($roundData, $this->defaultConfig);

        $this->assertIsArray($result);
        $this->assertContains('Invalid trick count for team_1', $result);
    }

    public function testValidateMisereDisabled(): void
    {
        $config = $this->defaultConfig;
        $config['misere_enabled'] = false;

        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => 'misere',
            'tricks_won' => ['team_1' => 0, 'team_2' => 10],
        ];

        $result = $this->engine->validateRoundData($roundData, $config);

        $this->assertIsArray($result);
        $this->assertContains('Misère is not enabled for this game', $result);
    }

    public function testValidateOpenMisereDisabled(): void
    {
        $config = $this->defaultConfig;
        $config['open_misere_enabled'] = false;

        $roundData = [
            'bidder_team' => 'team_1',
            'bid_key' => 'open_misere',
            'tricks_won' => ['team_1' => 0, 'team_2' => 10],
        ];

        $result = $this->engine->validateRoundData($roundData, $config);

        $this->assertIsArray($result);
        $this->assertContains('Open Misère is not enabled for this game', $result);
    }

    // ── getAvailableBids ──────────────────────────────────────────

    public function testGetAvailableBidsIncludesAllStandard(): void
    {
        $bids = FiveHundredEngine::getAvailableBids();

        // 5 tricks levels (6-10) * 5 suits = 25 standard bids + misère + open misère = 27
        $this->assertCount(27, $bids);
    }

    public function testGetAvailableBidsWithoutMisere(): void
    {
        $bids = FiveHundredEngine::getAvailableBids(['misere_enabled' => false]);

        // 25 standard + open misère = 26
        $this->assertCount(26, $bids);
        $misereKeys = array_column($bids, 'key');
        $this->assertNotContains('misere', $misereKeys);
        $this->assertContains('open_misere', $misereKeys);
    }

    public function testGetAvailableBidsWithoutOpenMisere(): void
    {
        $bids = FiveHundredEngine::getAvailableBids(['open_misere_enabled' => false]);

        // 25 standard + misère = 26
        $this->assertCount(26, $bids);
        $misereKeys = array_column($bids, 'key');
        $this->assertContains('misere', $misereKeys);
        $this->assertNotContains('open_misere', $misereKeys);
    }

    public function testGetAvailableBidsWithNeitherMisere(): void
    {
        $bids = FiveHundredEngine::getAvailableBids([
            'misere_enabled' => false,
            'open_misere_enabled' => false,
        ]);

        // 25 standard bids only
        $this->assertCount(25, $bids);
    }

    // ── getBidValue ───────────────────────────────────────────────

    public function testGetBidValueValidKeys(): void
    {
        $this->assertEquals(40, FiveHundredEngine::getBidValue('6_spades'));
        $this->assertEquals(200, FiveHundredEngine::getBidValue('7_hearts'));
        $this->assertEquals(520, FiveHundredEngine::getBidValue('10_no_trump'));
        $this->assertEquals(250, FiveHundredEngine::getBidValue('misere'));
        $this->assertEquals(500, FiveHundredEngine::getBidValue('open_misere'));
    }

    public function testGetBidValueInvalidKey(): void
    {
        $this->assertEquals(0, FiveHundredEngine::getBidValue('invalid'));
    }

    public function testGetBidValueCustomTable(): void
    {
        $customTable = ['custom_bid' => 999];
        $this->assertEquals(999, FiveHundredEngine::getBidValue('custom_bid', $customTable));
    }

    // ── getDefaultConfig ──────────────────────────────────────────

    public function testGetDefaultConfigStructure(): void
    {
        $config = $this->engine->getDefaultConfig();

        $this->assertEquals('five_hundred', $config['engine']);
        $this->assertEquals('high_wins', $config['scoring_direction']);
        $this->assertTrue($config['teams']['enabled']);
        $this->assertEquals(2, $config['teams']['size']);
        $this->assertEquals(500, $config['target_score']);
        $this->assertEquals(-500, $config['lose_score']);
        $this->assertTrue($config['track_dealer']);
        $this->assertArrayHasKey('bid_table', $config);
        $this->assertArrayHasKey('scoring_rules', $config);
        $this->assertArrayHasKey('options', $config);
    }

    // ── getConfigOptions ──────────────────────────────────────────

    public function testGetConfigOptionsReturnsExpectedKeys(): void
    {
        $options = $this->engine->getConfigOptions();

        $keys = array_column($options, 'key');
        $this->assertContains('kitty_size', $keys);
        $this->assertContains('misere_enabled', $keys);
        $this->assertContains('open_misere_enabled', $keys);
    }

    // ── getRequiredInputs ─────────────────────────────────────────

    public function testGetRequiredInputsReturnsThreeFields(): void
    {
        $inputs = $this->engine->getRequiredInputs($this->defaultConfig);

        $this->assertCount(3, $inputs);
        $keys = array_column($inputs, 'key');
        $this->assertContains('bidder_team', $keys);
        $this->assertContains('bid_key', $keys);
        $this->assertContains('tricks_won', $keys);
    }

    // ── Avondale bid table completeness ───────────────────────────

    public function testBidTableCoversAllValues(): void
    {
        // Verify the bid table has all 25 standard bids + 2 special = 27 entries
        $bids = FiveHundredEngine::getAvailableBids();
        $this->assertCount(27, $bids);

        // Verify lowest and highest standard bids
        $this->assertEquals(40, FiveHundredEngine::getBidValue('6_spades'));   // lowest
        $this->assertEquals(520, FiveHundredEngine::getBidValue('10_no_trump')); // highest

        // Verify values increase across suits for same trick count
        $this->assertLessThan(
            FiveHundredEngine::getBidValue('6_clubs'),
            FiveHundredEngine::getBidValue('6_spades'),
        );
        $this->assertLessThan(
            FiveHundredEngine::getBidValue('6_diamonds'),
            FiveHundredEngine::getBidValue('6_clubs'),
        );
    }
}
