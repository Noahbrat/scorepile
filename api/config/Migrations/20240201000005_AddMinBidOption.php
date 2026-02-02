<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add min_bid option to 500 scoring config
 */
class AddMinBidOption extends AbstractMigration
{
    public function up(): void
    {
        $rows = $this->fetchAll(
            "SELECT id, scoring_config FROM game_types WHERE name = '500 (4-Player Partners)'"
        );

        foreach ($rows as $row) {
            $config = json_decode($row['scoring_config'], true);
            if (!$config || !isset($config['options'])) {
                continue;
            }

            // Check if min_bid already exists
            $hasMinBid = false;
            foreach ($config['options'] as $opt) {
                if ($opt['key'] === 'min_bid') {
                    $hasMinBid = true;
                    break;
                }
            }

            if (!$hasMinBid) {
                // Insert min_bid option after kitty_size (position 1)
                $minBidOption = [
                    'key' => 'min_bid',
                    'label' => 'Minimum Bid',
                    'type' => 'select',
                    'choices' => [
                        ['value' => 6, 'label' => '6 tricks'],
                        ['value' => 7, 'label' => '7 tricks'],
                    ],
                    'default' => 7,
                ];
                array_splice($config['options'], 1, 0, [$minBidOption]);
            }

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

            $config['options'] = array_values(array_filter(
                $config['options'],
                fn($opt) => $opt['key'] !== 'min_bid'
            ));

            $encoded = json_encode($config);
            $this->execute(sprintf(
                "UPDATE game_types SET scoring_config = '%s' WHERE id = %d",
                addslashes($encoded),
                $row['id']
            ));
        }
    }
}
