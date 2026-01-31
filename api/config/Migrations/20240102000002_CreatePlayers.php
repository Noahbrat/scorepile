<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create players table
 */
class CreatePlayers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('players');

        $table
            ->addColumn('user_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('color', 'string', [
                'limit' => 7,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('avatar_emoji', 'string', [
                'limit' => 10,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['user_id'])
            ->addIndex(['user_id', 'name'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
