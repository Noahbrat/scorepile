<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create items table — example CRUD resource
 */
class CreateItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('items');

        $table
            ->addColumn('user_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('title', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('status', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'active',
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['user_id'])
            ->addIndex(['status'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
