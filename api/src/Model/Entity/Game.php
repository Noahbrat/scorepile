<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Game Entity
 *
 * @property int $id
 * @property string $user_id
 * @property int|null $game_type_id
 * @property string $name
 * @property string $status
 * @property string|null $notes
 * @property array|null $game_config
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\GameType $game_type
 * @property \App\Model\Entity\GamePlayer[] $game_players
 * @property \App\Model\Entity\Round[] $rounds
 */
class Game extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'game_type_id' => true,
        'name' => true,
        'status' => true,
        'notes' => true,
        'game_config' => true,
        'completed_at' => true,
    ];
}
