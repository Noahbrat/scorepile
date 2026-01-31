<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create rounds table
 */
class CreateRounds extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('rounds');

        $table
            ->addColumn('game_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('round_number', 'integer', [
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['game_id'])
            ->addIndex(['game_id', 'round_number'], ['unique' => true])
            ->addForeignKey('game_id', 'games', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
