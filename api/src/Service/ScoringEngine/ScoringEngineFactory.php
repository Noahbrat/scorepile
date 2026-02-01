<?php
declare(strict_types=1);

namespace App\Service\ScoringEngine;

use InvalidArgumentException;

class ScoringEngineFactory
{
    private static array $engines = [];

    public static function create(string $engineType): ScoringEngineInterface
    {
        if (isset(self::$engines[$engineType])) {
            return self::$engines[$engineType];
        }

        $engine = match ($engineType) {
            'simple', '' => new SimpleScoringEngine(),
            'five_hundred' => new FiveHundredEngine(),
            default => throw new InvalidArgumentException("Unknown scoring engine: {$engineType}"),
        };

        self::$engines[$engineType] = $engine;

        return $engine;
    }

    /**
     * Get engine for a game based on its game type's scoring config.
     */
    public static function forGameType(?array $scoringConfig): ScoringEngineInterface
    {
        $engineType = $scoringConfig['engine'] ?? 'simple';

        return self::create($engineType);
    }
}
