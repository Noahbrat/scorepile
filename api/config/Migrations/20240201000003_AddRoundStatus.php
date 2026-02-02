<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add status column to rounds table for bid/score separation.
 *
 * Values: 'bidding', 'playing', 'scoring', 'completed'
 * Default: 'completed' for backward compatibility with existing rounds.
 */
class AddRoundStatus extends AbstractMigration
{
    public function up(): void
    {
        $this->table('rounds')
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'completed',
                'after' => 'round_data',
            ])
            ->addIndex(['status'])
            ->addIndex(['game_id', 'status'])
            ->update();
    }

    public function down(): void
    {
        $this->table('rounds')
            ->removeIndexByName('rounds_status')
            ->removeIndexByName('rounds_game_id_status')
            ->removeColumn('status')
            ->update();
    }
}
