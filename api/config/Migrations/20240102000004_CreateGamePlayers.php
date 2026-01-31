<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create game_players join table
 */
class CreateGamePlayers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('game_players');

        $table
            ->addColumn('game_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('player_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('final_rank', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('total_score', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => false,
                'default' => 0,
            ])
            ->addColumn('is_winner', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['game_id'])
            ->addIndex(['player_id'])
            ->addIndex(['game_id', 'player_id'], ['unique' => true])
            ->addForeignKey('game_id', 'games', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('player_id', 'players', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
