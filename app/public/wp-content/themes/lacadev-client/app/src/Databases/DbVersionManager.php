<?php

namespace App\Databases;

/**
 * Prevents dbDelta() from running on every request.
 */
class DbVersionManager
{
    public static function maybeInstall(string $currentVersion, string $optionKey, array $installers): void
    {
        $installed = get_option($optionKey, '0.0.0');
        if (version_compare($installed, $currentVersion, '>=')) {
            return;
        }
        foreach ($installers as $installer) {
            $installer();
        }
        update_option($optionKey, $currentVersion, false);
    }

    public static function forceInstall(string $currentVersion, string $optionKey, array $installers): void
    {
        foreach ($installers as $installer) {
            $installer();
        }
        update_option($optionKey, $currentVersion, false);
    }

    public static function installedVersion(string $optionKey): ?string
    {
        $v = get_option($optionKey, null);
        return $v !== null ? (string) $v : null;
    }

    public static function reset(string $optionKey): void
    {
        delete_option($optionKey);
    }
}
