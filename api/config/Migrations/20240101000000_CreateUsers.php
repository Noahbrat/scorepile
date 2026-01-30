<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create users table
 */
class CreateUsers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'uuid', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('email', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('username', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('password', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('first_name', 'string', [
                'limit' => 100,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('last_name', 'string', [
                'limit' => 100,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('is_superuser', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('role', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => 'user',
            ])
            ->addColumn('password_reset_token', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('password_reset_expires', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['username'], ['unique' => true])
            ->create();
    }
}
