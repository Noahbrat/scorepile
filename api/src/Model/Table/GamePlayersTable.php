<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GamePlayers Table
 *
 * @method \App\Model\Entity\GamePlayer newEmptyEntity()
 * @method \App\Model\Entity\GamePlayer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 */
class GamePlayersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('game_players');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Games', [
            'foreignKey' => 'game_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Players', [
            'foreignKey' => 'player_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('Scores', [
            'foreignKey' => 'game_player_id',
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
            ->integer('player_id')
            ->requirePresence('player_id', 'create')
            ->notEmptyString('player_id');

        $validator
            ->integer('final_rank')
            ->allowEmptyString('final_rank');

        $validator
            ->decimal('total_score')
            ->notEmptyString('total_score');

        $validator
            ->boolean('is_winner');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('game_id', 'Games'), ['message' => 'Game does not exist']);
        $rules->add($rules->existsIn('player_id', 'Players'), ['message' => 'Player does not exist']);
        $rules->add($rules->isUnique(['game_id', 'player_id']), ['message' => 'This player is already in this game']);

        return $rules;
    }
}
