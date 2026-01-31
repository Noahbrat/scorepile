<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Rounds Table
 *
 * @method \App\Model\Entity\Round newEmptyEntity()
 * @method \App\Model\Entity\Round get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 */
class RoundsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rounds');
        $this->setDisplayField('round_number');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Games', [
            'foreignKey' => 'game_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('Scores', [
            'foreignKey' => 'round_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('game_id')
            ->requirePresence('game_id', 'create')
            ->notEmptyString('game_id');

        $validator
            ->integer('round_number')
            ->requirePresence('round_number', 'create')
            ->notEmptyString('round_number')
            ->greaterThan('round_number', 0, 'Round number must be positive');

        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->allowEmptyString('name');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('game_id', 'Games'), ['message' => 'Game does not exist']);
        $rules->add($rules->isUnique(['game_id', 'round_number']), ['message' => 'This round number already exists for this game']);

        return $rules;
    }
}
