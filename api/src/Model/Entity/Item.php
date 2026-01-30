<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Item Entity
 *
 * Example entity demonstrating the pattern.
 *
 * @property int $id
 * @property string $user_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\User $user
 */
class Item extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'title' => true,
        'description' => true,
        'status' => true,
        'created' => true,
        'modified' => true,
    ];
}
