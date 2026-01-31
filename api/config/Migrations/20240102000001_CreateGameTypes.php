<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create game_types table
 */
class CreateGameTypes extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('game_types');

        $table
            ->addColumn('user_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('scoring_direction', 'string', [
                'limit' => 10,
                'null' => false,
                'default' => 'high_wins',
            ])
            ->addColumn('default_rounds', 'integer', [
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
