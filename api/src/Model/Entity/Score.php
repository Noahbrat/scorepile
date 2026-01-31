<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Score Entity
 *
 * @property int $id
 * @property int $round_id
 * @property int $game_player_id
 * @property string $points
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Round $round
 * @property \App\Model\Entity\GamePlayer $game_player
 */
class Score extends Entity
{
    protected array $_accessible = [
        'round_id' => true,
        'game_player_id' => true,
        'points' => true,
        'notes' => true,
    ];
}
