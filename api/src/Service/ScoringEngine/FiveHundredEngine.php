<?php
declare(strict_types=1);

namespace App\Service\ScoringEngine;

/**
 * 500 Card Game Scoring Engine
 *
 * Implements Avondale scoring table for the classic Australian card game.
 * Supports 4-player partnerships, Misère, and Open Misère.
 */
class FiveHundredEngine implements ScoringEngineInterface
{
    /**
     * Avondale scoring table: "{tricks}_{suit}" => points
     */
    private const BID_TABLE = [
        '6_spades' => 40, '6_clubs' => 60, '6_diamonds' => 80, '6_hearts' => 100, '6_no_trump' => 120,
        '7_spades' => 140, '7_clubs' => 160, '7_diamonds' => 180, '7_hearts' => 200, '7_no_trump' => 220,
        '8_spades' => 240, '8_clubs' => 260, '8_diamonds' => 280, '8_hearts' => 300, '8_no_trump' => 320,
        '9_spades' => 340, '9_clubs' => 360, '9_diamonds' => 380, '9_hearts' => 400, '9_no_trump' => 420,
        '10_spades' => 440, '10_clubs' => 460, '10_diamonds' => 480, '10_hearts' => 500, '10_no_trump' => 520,
        'misere' => 250,
        'open_misere' => 500,
    ];

    private const SUITS = ['spades', 'clubs', 'diamonds', 'hearts', 'no_trump'];
    private const SUIT_SYMBOLS = [
        'spades' => "\u{2660}",
        'clubs' => "\u{2663}",
        'diamonds' => "\u{2666}",
        'hearts' => "\u{2665}",
        'no_trump' => 'NT',
    ];

    public function calculateRoundScores(array $roundData, array $gameConfig): array
    {
        $bidKey = $roundData['bid_key'] ?? null;
        $bidderTeam = $roundData['bidder_team'] ?? null;
        $tricksWon = $roundData['tricks_won'] ?? [];

        if (!$bidKey || !$bidderTeam) {
            return [];
        }

        $bidTable = $gameConfig['bid_table'] ?? self::BID_TABLE;
        $bidValue = $bidTable[$bidKey] ?? 0;

        if ($bidValue === 0) {
            return [];
        }

        $scores = [];
        $isMisere = in_array($bidKey, ['misere', 'open_misere']);

        if ($isMisere) {
            $scores = $this->calculateMisereScores($roundData, $bidValue, $bidderTeam, $tricksWon);
        } else {
            $scores = $this->calculateNormalScores($roundData, $bidValue, $bidderTeam, $tricksWon);
        }

        return $scores;
    }

    private function calculateNormalScores(array $roundData, int $bidValue, string $bidderTeam, array $tricksWon): array
    {
        $bidTricks = $roundData['bid_tricks'] ?? 0;
        $bidderTricksWon = $tricksWon[$bidderTeam] ?? 0;
        $bidMade = $bidderTricksWon >= $bidTricks;

        $scores = [];
        $opponentPointsPerTrick = 10;

        foreach ($tricksWon as $team => $tricks) {
            if ($team === $bidderTeam) {
                $scores[$team] = $bidMade ? $bidValue : -$bidValue;
            } else {
                $scores[$team] = $tricks * $opponentPointsPerTrick;
            }
        }

        return [
            'scores' => $scores,
            'bid_made' => $bidMade,
            'bid_value' => $bidValue,
        ];
    }

    private function calculateMisereScores(array $roundData, int $bidValue, string $bidderTeam, array $tricksWon): array
    {
        // Misère: bidder must take 0 tricks to win
        $bidderTricksWon = $tricksWon[$bidderTeam] ?? 0;
        $bidMade = $bidderTricksWon === 0;

        $scores = [];
        foreach ($tricksWon as $team => $tricks) {
            if ($team === $bidderTeam) {
                $scores[$team] = $bidMade ? $bidValue : -$bidValue;
            } else {
                // Opponents don't score tricks on misère
                $scores[$team] = 0;
            }
        }

        return [
            'scores' => $scores,
            'bid_made' => $bidMade,
            'bid_value' => $bidValue,
        ];
    }

    public function getRequiredInputs(array $gameConfig): array
    {
        $inputs = [
            [
                'key' => 'bidder_team',
                'label' => 'Bidding Team',
                'type' => 'select',
                'per_player' => false,
            ],
            [
                'key' => 'bid_key',
                'label' => 'Bid',
                'type' => 'bid_grid',
                'per_player' => false,
            ],
            [
                'key' => 'tricks_won',
                'label' => 'Tricks Won',
                'type' => 'tricks',
                'per_player' => false,
                'per_team' => true,
            ],
        ];

        return $inputs;
    }

    public function validateRoundData(array $roundData, array $gameConfig): array|true
    {
        $errors = [];

        if (empty($roundData['bidder_team'])) {
            $errors[] = 'Bidding team is required';
        }

        if (empty($roundData['bid_key'])) {
            $errors[] = 'Bid is required';
        } else {
            $bidTable = $gameConfig['bid_table'] ?? self::BID_TABLE;
            if (!isset($bidTable[$roundData['bid_key']])) {
                $errors[] = 'Invalid bid';
            }

            // Validate misère options
            $isMisere = $roundData['bid_key'] === 'misere';
            $isOpenMisere = $roundData['bid_key'] === 'open_misere';

            if ($isMisere && !($gameConfig['misere_enabled'] ?? true)) {
                $errors[] = 'Misère is not enabled for this game';
            }
            if ($isOpenMisere && !($gameConfig['open_misere_enabled'] ?? true)) {
                $errors[] = 'Open Misère is not enabled for this game';
            }
        }

        if (empty($roundData['tricks_won']) || !is_array($roundData['tricks_won'])) {
            $errors[] = 'Tricks won is required';
        } else {
            $totalTricks = array_sum($roundData['tricks_won']);
            if ($totalTricks !== 10) {
                $errors[] = 'Total tricks must equal 10';
            }

            foreach ($roundData['tricks_won'] as $team => $tricks) {
                if ($tricks < 0 || $tricks > 10) {
                    $errors[] = "Invalid trick count for {$team}";
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    public function getDefaultConfig(): array
    {
        return [
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
            'options' => $this->getConfigOptions(),
            'bid_table' => self::BID_TABLE,
            'scoring_rules' => [
                'bid_won' => 'bid_value',
                'bid_lost' => '-bid_value',
                'opponent_per_trick' => 10,
            ],
        ];
    }

    public function getConfigOptions(): array
    {
        return [
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
        ];
    }

    /**
     * Get the bid value from the bid table.
     */
    public static function getBidValue(string $bidKey, array $bidTable = []): int
    {
        $table = !empty($bidTable) ? $bidTable : self::BID_TABLE;

        return $table[$bidKey] ?? 0;
    }

    /**
     * Get all available bids as a structured array for the UI.
     */
    public static function getAvailableBids(array $gameConfig = []): array
    {
        $misereEnabled = $gameConfig['misere_enabled'] ?? true;
        $openMisereEnabled = $gameConfig['open_misere_enabled'] ?? true;

        $bids = [];

        // Standard bids (6-10 tricks, each suit)
        for ($tricks = 6; $tricks <= 10; $tricks++) {
            foreach (self::SUITS as $suit) {
                $key = "{$tricks}_{$suit}";
                $bids[] = [
                    'key' => $key,
                    'tricks' => $tricks,
                    'suit' => $suit,
                    'suit_symbol' => self::SUIT_SYMBOLS[$suit],
                    'value' => self::BID_TABLE[$key],
                    'label' => "{$tricks}" . self::SUIT_SYMBOLS[$suit],
                    'type' => 'normal',
                ];
            }
        }

        // Misère (value 250, between 8♠ and 8♣)
        if ($misereEnabled) {
            $bids[] = [
                'key' => 'misere',
                'tricks' => null,
                'suit' => null,
                'suit_symbol' => null,
                'value' => 250,
                'label' => 'Misère',
                'type' => 'misere',
            ];
        }

        // Open Misère (value 500, between 10♦ and 10♥)
        if ($openMisereEnabled) {
            $bids[] = [
                'key' => 'open_misere',
                'tricks' => null,
                'suit' => null,
                'suit_symbol' => null,
                'value' => 500,
                'label' => 'Open Misère',
                'type' => 'open_misere',
            ];
        }

        return $bids;
    }
}
