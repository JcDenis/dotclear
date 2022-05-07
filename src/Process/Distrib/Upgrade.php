<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Distrib;

// Dotclear\Process\Distrib\Upgrade
use Dotclear\App;
use Dotclear\Database\Structure;
use Dotclear\Exception\DistribException;
use Exception;

/**
 * Dotclear distribution upgrade methods.
 *
 * @ingroup  Distrib
 */
class Upgrade
{
    public function doUpgrade(): int
    {
        if (!App::core()->version()->exists('core')) {
            return -1;
        }

        if (version_compare(App::core()->version()->get('core'), App::core()->config()->get('core_version'), '<') == 1 || !App::core()->production()) {
            try {
                if (App::core()->con()->driver() == 'sqlite') {
                    return -1; // Need to find a way to upgrade sqlite database
                }

                // Database upgrade
                $_s = new Structure(App::core()->con(), App::core()->prefix);
                Distrib::getDatabaseStructure($_s);

                $si      = new Structure(App::core()->con(), App::core()->prefix);
                $changes = $si->synchronize($_s);

                /* Some other upgrades
                ------------------------------------ */
                $cleanup_sessions = $this->growUp(App::core()->version()->get('core'));

                // Drop content from session table if changes or if needed
                if (0 != $changes || $cleanup_sessions) {
                    App::core()->con()->execute('DELETE FROM ' . App::core()->prefix . 'session ');
                }

                // Empty templates cache directory
                try {
                    App::core()->emptyTemplatesCache();
                } catch (\Exception) {
                }

                return $changes;
            } catch (Exception $e) {
                throw new DistribException(
                    __('Something went wrong with auto upgrade:') . ' ' . $e->getMessage()
                );
            }
        }

        // No upgrade?
        return -1;
    }

    public function growUp(string $version): bool
    {
        if (empty($version)) {
            return false;
        }

        $cleanup_sessions = false; // update it in a step that needed sessions to be removed

        // no growup for now :)

        App::core()->version()->set('core', App::core()->config()->get('core_version'));
        Distrib::setBlogDefaultSettings();

        return $cleanup_sessions;
    }
}
