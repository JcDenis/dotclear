<?php
/**
 * @class Dotclear\Process\Distrib\Upgrade
 * @brief Dotclear distribution upgrade class
 *
 * @todo no files remove < dcns as entire structure change
 *
 * @package Dotclear
 * @subpackage Distrib
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Distrib;

use Dotclear\Database\Schema;
use Dotclear\Database\Structure;
use Dotclear\Process\Distrib\Distrib;
use Dotclear\Exception\DistribException;
use Dotclear\File\Files;
use Dotclear\File\Path;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

if (!defined('DOTCLEAR_OLD_ROOT_DIR')) {
    define('DOTCLEAR_OLD_ROOT_DIR', DOTCLEAR_ROOT_DIR . '/../');
}

class Upgrade
{
    public static function dotclearUpgrade(): bool
    {
        $upgrade = new Upgrade();

        return $upgrade->doUpgrade();
    }

    protected function doUpgrade()
    {
        $version = dotclear()->version()->get('core');

        if ($version === null) {
            return false;
        }

        if (version_compare($version, dotclear()->config()->core_version, '<') == 1 || strpos(dotclear()->config()->core_version, 'dev')) {
            try {
                if (dotclear()->con()->driver() == 'sqlite') {
                    return false; // Need to find a way to upgrade sqlite database
                }

                # Database upgrade
                $_s = new Structure(dotclear()->con(), dotclear()->prefix);
                Distrib::getDatabaseStructure($_s);

                $si      = new Structure(dotclear()->con(), dotclear()->prefix);
                $changes = $si->synchronize($_s);

                /* Some other upgrades
                ------------------------------------ */
                $cleanup_sessions = $this->growUp($version);

                # Drop content from session table if changes or if needed
                if ($changes != 0 || $cleanup_sessions) {
                    dotclear()->con()->execute('DELETE FROM ' . dotclear()->prefix . 'session ');
                }

                # Empty templates cache directory
                try {
                    dotclear()->emptyTemplatesCache();
                } catch (\Exception $e) {
                }

                return (bool) $changes;
            } catch (\Exception $e) {
                throw new DistribException(
                    __('Something went wrong with auto upgrade:') . ' ' . $e->getMessage()
                );
            }
        }

        # No upgrade?
        return false;
    }

    public function growUp(?string $version): bool
    {
        if ($version === null) {
            return false;
        }

        $cleanup_sessions = false; // update it in a step that needed sessions to be removed

        // no growup for now :)

        dotclear()->version()->set('core', dotclear()->config()->core_version);
        Distrib::setBlogDefaultSettings();

        return $cleanup_sessions;
    }
}