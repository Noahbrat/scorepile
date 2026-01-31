<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create games table
 */
class CreateGames extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('games');

        $table
            ->addColumn('user_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('game_type_id', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'active',
            ])
            ->addColumn('notes', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('completed_at', 'datetime', [
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
            ->addIndex(['status'])
            ->addIndex(['game_type_id'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('game_type_id', 'game_types', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
