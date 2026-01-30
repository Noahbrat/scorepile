<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

/**
 * User Entity
 *
 * @property string $id
 * @property string $email
 * @property string $username
 * @property string $password
 * @property string|null $first_name
 * @property string|null $last_name
 * @property bool $active
 * @property bool $is_superuser
 * @property string|null $role
 * @property string|null $password_reset_token
 * @property \Cake\I18n\DateTime|null $password_reset_expires
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class User extends Entity
{
    protected array $_accessible = [
        'id' => true,
        'email' => true,
        'username' => true,
        'password' => true,
        'first_name' => true,
        'last_name' => true,
        'active' => true,
        'is_superuser' => true,
        'role' => true,
        'password_reset_token' => true,
        'password_reset_expires' => true,
        'created' => true,
        'modified' => true,
    ];

    protected array $_hidden = [
        'password',
        'password_reset_token',
    ];

    /**
     * Hash password before saving
     */
    protected function _setPassword(string $password): string
    {
        if (strlen($password) > 0) {
            return (new DefaultPasswordHasher())->hash($password);
        }

        return $password;
    }

    public function isAdmin(): bool
    {
        return $this->is_superuser || $this->role === 'admin';
    }

    public function isSuperuser(): bool
    {
        return (bool)$this->is_superuser;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function canAccessAdmin(): bool
    {
        return $this->isAdmin() && $this->active;
    }
}
