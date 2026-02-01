<?php
declare(strict_types=1);

namespace App\Service\ScoringEngine;

interface ScoringEngineInterface
{
    /**
     * Calculate scores for a round based on round data and game configuration.
     *
     * @param array $roundData The round data (bids, tricks, etc.)
     * @param array $gameConfig The game-level configuration overrides
     * @return array Calculated scores keyed by team or game_player_id
     */
    public function calculateRoundScores(array $roundData, array $gameConfig): array;

    /**
     * Get the required inputs for a round entry form.
     *
     * @param array $gameConfig The game-level configuration
     * @return array Description of required inputs
     */
    public function getRequiredInputs(array $gameConfig): array;

    /**
     * Validate round data before saving.
     *
     * @param array $roundData The round data to validate
     * @param array $gameConfig The game-level configuration
     * @return array|true True if valid, array of errors if invalid
     */
    public function validateRoundData(array $roundData, array $gameConfig): array|true;

    /**
     * Get the default configuration for this engine.
     *
     * @return array Default config values
     */
    public function getDefaultConfig(): array;

    /**
     * Get the configurable options for this engine.
     *
     * @return array Options that can be customized per game
     */
    public function getConfigOptions(): array;
}
