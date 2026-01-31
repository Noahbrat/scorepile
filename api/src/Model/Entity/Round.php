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
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Game $game
 * @property \App\Model\Entity\Score[] $scores
 */
class Round extends Entity
{
    protected array $_accessible = [
        'game_id' => true,
        'round_number' => true,
        'name' => true,
    ];
}
