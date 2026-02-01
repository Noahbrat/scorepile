<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add scoring engine support columns:
 * - game_types: scoring_config (JSON), is_system (bool), nullable user_id
 * - rounds: dealer_game_player_id, round_data (JSON)
 * - games: game_config (JSON)
 * - game_players: team (int)
 */
class AddScoringEngineSupport extends AbstractMigration
{
    public function up(): void
    {
        // ── game_types ──────────────────────────────────────────────
        // Drop existing FK and unique index on user_id before making it nullable
        $this->table('game_types')
            ->dropForeignKey('user_id')
            ->save();

        $this->table('game_types')
            ->addColumn('scoring_config', 'json', [
                'null' => true,
                'default' => null,
                'after' => 'default_rounds',
            ])
            ->addColumn('is_system', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'scoring_config',
            ])
            ->changeColumn('user_id', 'uuid', [
                'null' => true,
                'default' => null,
            ])
            ->update();

        // Re-add FK with nullable support
        $this->table('game_types')
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->update();

        // Drop the old unique index on (user_id, name) since system types have null user_id
        // Phinx auto-named this 'user_id_2' (composite unique on user_id + name)
        $this->table('game_types')
            ->removeIndexByName('user_id_2')
            ->update();

        // ── rounds ──────────────────────────────────────────────────
        $this->table('rounds')
            ->addColumn('dealer_game_player_id', 'integer', [
                'null' => true,
                'default' => null,
                'after' => 'name',
            ])
            ->addColumn('round_data', 'json', [
                'null' => true,
                'default' => null,
                'after' => 'dealer_game_player_id',
            ])
            ->addIndex(['dealer_game_player_id'])
            ->addForeignKey('dealer_game_player_id', 'game_players', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ])
            ->update();

        // ── games ───────────────────────────────────────────────────
        $this->table('games')
            ->addColumn('game_config', 'json', [
                'null' => true,
                'default' => null,
                'after' => 'notes',
            ])
            ->update();

        // ── game_players ────────────────────────────────────────────
        $this->table('game_players')
            ->addColumn('team', 'integer', [
                'null' => true,
                'default' => null,
                'after' => 'is_winner',
            ])
            ->update();
    }

    public function down(): void
    {
        // ── game_players ────────────────────────────────────────────
        $this->table('game_players')
            ->removeColumn('team')
            ->update();

        // ── games ───────────────────────────────────────────────────
        $this->table('games')
            ->removeColumn('game_config')
            ->update();

        // ── rounds ──────────────────────────────────────────────────
        $this->table('rounds')
            ->dropForeignKey('dealer_game_player_id')
            ->removeColumn('dealer_game_player_id')
            ->removeColumn('round_data')
            ->update();

        // ── game_types ──────────────────────────────────────────────
        $this->table('game_types')
            ->dropForeignKey('user_id')
            ->save();

        $this->table('game_types')
            ->removeColumn('scoring_config')
            ->removeColumn('is_system')
            ->changeColumn('user_id', 'uuid', [
                'null' => false,
            ])
            ->update();

        // Re-add original FK and unique index
        $this->table('game_types')
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addIndex(['user_id', 'name'], ['unique' => true, 'name' => 'user_id_2'])
            ->update();
    }
}
