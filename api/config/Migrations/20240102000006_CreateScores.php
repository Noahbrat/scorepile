<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create scores table
 */
class CreateScores extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('scores');

        $table
            ->addColumn('round_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('game_player_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('points', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => false,
            ])
            ->addColumn('notes', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['round_id'])
            ->addIndex(['game_player_id'])
            ->addIndex(['round_id', 'game_player_id'], ['unique' => true])
            ->addForeignKey('round_id', 'rounds', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('game_player_id', 'game_players', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
