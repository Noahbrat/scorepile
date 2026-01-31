<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Player Entity
 *
 * @property int $id
 * @property string $user_id
 * @property string $name
 * @property string|null $color
 * @property string|null $avatar_emoji
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\GamePlayer[] $game_players
 */
class Player extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'name' => true,
        'color' => true,
        'avatar_emoji' => true,
    ];
}
