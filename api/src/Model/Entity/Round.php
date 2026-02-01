<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Round Entity
 *
 * @property int $id
 * @property int $game_id
 * @property int $round_number
 * @property string|null $name
 * @property int|null $dealer_game_player_id
 * @property array|null $round_data
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Game $game
 * @property \App\Model\Entity\GamePlayer|null $dealer_game_player
 * @property \App\Model\Entity\Score[] $scores
 */
class Round extends Entity
{
    protected array $_accessible = [
        'game_id' => true,
        'round_number' => true,
        'name' => true,
        'dealer_game_player_id' => true,
        'round_data' => true,
    ];
}
