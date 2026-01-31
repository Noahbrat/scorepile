<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Users Table
 *
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 */
class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('email');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Games', [
            'foreignKey' => 'user_id',
        ]);

        $this->hasMany('GameTypes', [
            'foreignKey' => 'user_id',
        ]);

        $this->hasMany('Players', [
            'foreignKey' => 'user_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->email('email')
            ->requirePresence('email', 'create')
            ->notEmptyString('email')
            ->maxLength('email', 255)
            ->add('email', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('username')
            ->maxLength('username', 100)
            ->requirePresence('username', 'create')
            ->notEmptyString('username')
            ->minLength('username', 3)
            ->alphaNumeric('username', 'Username can only contain letters and numbers')
            ->add('username', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('password')
            ->maxLength('password', 255)
            ->requirePresence('password', 'create')
            ->notEmptyString('password')
            ->minLength('password', 8, 'Password must be at least 8 characters long')
            ->add('password', 'complex', [
                'rule' => function ($value, $context) {
                    return (bool)preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $value);
                },
                'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number',
            ]);

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 100)
            ->allowEmptyString('first_name');

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 100)
            ->allowEmptyString('last_name');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']), ['message' => 'This email is already registered']);
        $rules->add($rules->isUnique(['username']), ['message' => 'This username is already taken']);

        return $rules;
    }
}
