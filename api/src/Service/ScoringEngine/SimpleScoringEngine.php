<?php
declare(strict_types=1);

namespace App\Service\ScoringEngine;

/**
 * Simple Scoring Engine — manual point entry per player per round.
 * No auto-calculation. This is the default/legacy behavior.
 */
class SimpleScoringEngine implements ScoringEngineInterface
{
    public function calculateRoundScores(array $roundData, array $gameConfig): array
    {
        // Simple engine: scores are entered directly, no calculation needed
        return $roundData['scores'] ?? [];
    }

    public function getRequiredInputs(array $gameConfig): array
    {
        return [
            [
                'key' => 'points',
                'label' => 'Points',
                'type' => 'number',
                'per_player' => true,
            ],
        ];
    }

    public function validateRoundData(array $roundData, array $gameConfig): array|true
    {
        return true;
    }

    public function getDefaultConfig(): array
    {
        return [
            'engine' => 'simple',
            'scoring_direction' => 'high_wins',
        ];
    }

    public function getConfigOptions(): array
    {
        return [];
    }
}
