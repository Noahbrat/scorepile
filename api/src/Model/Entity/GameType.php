<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GameType Entity
 *
 * @property int $id
 * @property string|null $user_id
 * @property string $name
 * @property string|null $description
 * @property string $scoring_direction
 * @property int|null $default_rounds
 * @property array|null $scoring_config
 * @property bool $is_system
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\User|null $user
 * @property \App\Model\Entity\Game[] $games
 */
class GameType extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'name' => true,
        'description' => true,
        'scoring_direction' => true,
        'default_rounds' => true,
        'scoring_config' => true,
        'is_system' => true,
    ];
}
