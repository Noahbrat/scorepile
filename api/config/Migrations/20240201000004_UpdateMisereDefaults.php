<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Update Misère option defaults to false and labels to include "Nullo"
 */
class UpdateMisereDefaults extends AbstractMigration
{
    public function up(): void
    {
        // Fetch the 500 game type and update its scoring_config options
        $rows = $this->fetchAll(
            "SELECT id, scoring_config FROM game_types WHERE name = '500 (4-Player Partners)'"
        );

        foreach ($rows as $row) {
            $config = json_decode($row['scoring_config'], true);
            if (!$config || !isset($config['options'])) {
                continue;
            }

            foreach ($config['options'] as &$opt) {
                if ($opt['key'] === 'misere_enabled') {
                    $opt['default'] = false;
                    $opt['label'] = 'Allow Misère / Nullo';
                }
                if ($opt['key'] === 'open_misere_enabled') {
                    $opt['default'] = false;
                    $opt['label'] = 'Allow Open Misère / Nullo';
                }
            }
            unset($opt);

            $encoded = json_encode($config);
            $this->execute(sprintf(
                "UPDATE game_types SET scoring_config = '%s' WHERE id = %d",
                addslashes($encoded),
                $row['id']
            ));
        }
    }

    public function down(): void
    {
        $rows = $this->fetchAll(
            "SELECT id, scoring_config FROM game_types WHERE name = '500 (4-Player Partners)'"
        );

        foreach ($rows as $row) {
            $config = json_decode($row['scoring_config'], true);
            if (!$config || !isset($config['options'])) {
                continue;
            }

            foreach ($config['options'] as &$opt) {
                if ($opt['key'] === 'misere_enabled') {
                    $opt['default'] = true;
                    $opt['label'] = 'Allow Misère';
                }
                if ($opt['key'] === 'open_misere_enabled') {
                    $opt['default'] = true;
                    $opt['label'] = 'Allow Open Misère';
                }
            }
            unset($opt);

            $encoded = json_encode($config);
            $this->execute(sprintf(
                "UPDATE game_types SET scoring_config = '%s' WHERE id = %d",
                addslashes($encoded),
                $row['id']
            ));
        }
    }
}
