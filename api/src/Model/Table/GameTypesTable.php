<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GameTypes Table
 *
 * @method \App\Model\Entity\GameType newEmptyEntity()
 * @method \App\Model\Entity\GameType get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 */
class GameTypesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('game_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('Games', [
            'foreignKey' => 'game_type_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('scoring_direction')
            ->inList('scoring_direction', ['high_wins', 'low_wins'], 'Invalid scoring direction')
            ->notEmptyString('scoring_direction');

        $validator
            ->integer('default_rounds')
            ->allowEmptyString('default_rounds')
            ->greaterThan('default_rounds', 0, 'Must be a positive number');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('user_id', 'Users'), ['message' => 'User does not exist']);
        $rules->add($rules->isUnique(['user_id', 'name']), ['message' => 'You already have a game type with this name']);

        return $rules;
    }
}
