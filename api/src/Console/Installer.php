<?php
declare(strict_types=1);

namespace App\Console;

use Composer\Script\Event;
use Exception;

/**
 * Provides installation hooks for when this application is installed via
 * composer. Customize this class to suit your needs.
 */
class Installer
{
    /**
     * Does some routine installation tasks so people don't have to.
     *
     * @param \Composer\Script\Event $event The composer event object.
     * @throws \Exception Exception raised by validator.
     * @return void
     */
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();
        $rootDir = dirname(dirname(__DIR__));

        static::createAppLocalConfig($rootDir, $io);
        static::createWritableDirectories($rootDir, $io);
        static::setFolderPermissions($rootDir, $io);
        static::setSecuritySalt($rootDir, $io);
    }

    /**
     * Create the config/app_local.php file if it doesn't exist.
     */
    public static function createAppLocalConfig(string $dir, $io): void
    {
        $appLocalConfig = $dir . '/config/app_local.php';
        $appLocalExampleConfig = $dir . '/config/app_local.example.php';

        if (!file_exists($appLocalConfig) && file_exists($appLocalExampleConfig)) {
            copy($appLocalExampleConfig, $appLocalConfig);
            $io->write('Created `config/app_local.php` file');
        }
    }

    /**
     * Create the `logs` and `tmp` directories.
     */
    public static function createWritableDirectories(string $dir, $io): void
    {
        foreach (['logs', 'tmp', 'tmp/cache', 'tmp/cache/models', 'tmp/cache/persistent', 'tmp/cache/views', 'tmp/sessions', 'tmp/tests'] as $path) {
            $fullPath = $dir . '/' . $path;
            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0775, true);
                $io->write("Created `{$path}` directory");
            }
        }
    }

    /**
     * Set folder permissions for tmp and logs.
     */
    public static function setFolderPermissions(string $dir, $io): void
    {
        $paths = [
            'logs',
            'tmp',
            'tmp/cache',
            'tmp/cache/models',
            'tmp/cache/persistent',
            'tmp/cache/views',
            'tmp/sessions',
            'tmp/tests',
        ];

        foreach ($paths as $path) {
            $fullPath = $dir . '/' . $path;
            if (file_exists($fullPath)) {
                chmod($fullPath, 0775);
            }
        }

        $io->write('Set Folder Permissions');
    }

    /**
     * Set the security.salt value in the application's config file.
     */
    public static function setSecuritySalt(string $dir, $io): void
    {
        $config = $dir . '/config/app_local.php';

        if (!file_exists($config)) {
            return;
        }

        $content = file_get_contents($config);

        $newKey = hash('sha256', random_bytes(64));
        $content = str_replace('__SALT__', $newKey, $content, $count);

        if ($count === 0) {
            return;
        }

        file_put_contents($config, $content);
        $io->write('Updated Security.salt value in config/app_local.php');
    }
}
