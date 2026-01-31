<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Scores Table
 *
 * @method \App\Model\Entity\Score newEmptyEntity()
 * @method \App\Model\Entity\Score get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 */
class ScoresTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('scores');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Rounds', [
            'foreignKey' => 'round_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('GamePlayers', [
            'foreignKey' => 'game_player_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('round_id')
            ->requirePresence('round_id', 'create')
            ->notEmptyString('round_id');

        $validator
            ->integer('game_player_id')
            ->requirePresence('game_player_id', 'create')
            ->notEmptyString('game_player_id');

        $validator
            ->decimal('points')
            ->requirePresence('points', 'create')
            ->notEmptyString('points');

        $validator
            ->scalar('notes')
            ->maxLength('notes', 255)
            ->allowEmptyString('notes');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('round_id', 'Rounds'), ['message' => 'Round does not exist']);
        $rules->add($rules->existsIn('game_player_id', 'GamePlayers'), ['message' => 'Game player does not exist']);
        $rules->add($rules->isUnique(['round_id', 'game_player_id']), ['message' => 'Score already exists for this player in this round']);

        return $rules;
    }
}
