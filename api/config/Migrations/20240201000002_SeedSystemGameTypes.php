<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Seed system game types: Simple Scoring and 500 (4-player Partners)
 */
class SeedSystemGameTypes extends AbstractMigration
{
    public function up(): void
    {
        $fiveHundredConfig = json_encode([
            'engine' => 'five_hundred',
            'scoring_direction' => 'high_wins',
            'win_condition' => 'first_to_target',
            'target_score' => 500,
            'lose_score' => -500,
            'track_dealer' => true,
            'teams' => [
                'enabled' => true,
                'size' => 2,
            ],
            'options' => [
                [
                    'key' => 'kitty_size',
                    'label' => 'Kitty Size',
                    'type' => 'select',
                    'choices' => [
                        ['value' => 3, 'label' => '3 cards'],
                        ['value' => 5, 'label' => '5 cards'],
                    ],
                    'default' => 3,
                ],
                [
                    'key' => 'misere_enabled',
                    'label' => 'Allow Misère',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'open_misere_enabled',
                    'label' => 'Allow Open Misère',
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
            'bid_table' => [
                '6_spades' => 40, '6_clubs' => 60, '6_diamonds' => 80, '6_hearts' => 100, '6_no_trump' => 120,
                '7_spades' => 140, '7_clubs' => 160, '7_diamonds' => 180, '7_hearts' => 200, '7_no_trump' => 220,
                '8_spades' => 240, '8_clubs' => 260, '8_diamonds' => 280, '8_hearts' => 300, '8_no_trump' => 320,
                '9_spades' => 340, '9_clubs' => 360, '9_diamonds' => 380, '9_hearts' => 400, '9_no_trump' => 420,
                '10_spades' => 440, '10_clubs' => 460, '10_diamonds' => 480, '10_hearts' => 500, '10_no_trump' => 520,
                'misere' => 250,
                'open_misere' => 500,
            ],
            'scoring_rules' => [
                'bid_won' => 'bid_value',
                'bid_lost' => '-bid_value',
                'opponent_per_trick' => 10,
            ],
        ]);

        $now = date('Y-m-d H:i:s');

        $this->table('game_types')->insert([
            [
                'user_id' => null,
                'name' => 'Simple Scoring',
                'description' => 'Manual point entry per player per round. No auto-calculation.',
                'scoring_direction' => 'high_wins',
                'default_rounds' => null,
                'scoring_config' => null,
                'is_system' => true,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'user_id' => null,
                'name' => '500 (4-Player Partners)',
                'description' => 'The classic Australian card game. Teams of 2, Avondale scoring table, first to 500 wins.',
                'scoring_direction' => 'high_wins',
                'default_rounds' => null,
                'scoring_config' => $fiveHundredConfig,
                'is_system' => true,
                'created' => $now,
                'modified' => $now,
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM game_types WHERE is_system = 1");
    }
}
