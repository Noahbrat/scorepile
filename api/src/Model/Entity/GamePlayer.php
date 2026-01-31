<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GamePlayer Entity
 *
 * @property int $id
 * @property int $game_id
 * @property int $player_id
 * @property int|null $final_rank
 * @property string $total_score
 * @property bool $is_winner
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Game $game
 * @property \App\Model\Entity\Player $player
 * @property \App\Model\Entity\Score[] $scores
 */
class GamePlayer extends Entity
{
    protected array $_accessible = [
        'game_id' => true,
        'player_id' => true,
        'final_rank' => true,
        'total_score' => true,
        'is_winner' => true,
    ];
}
