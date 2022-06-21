<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Distrib;

// Dotclear\Process\Distrib\Prepend
use Dotclear\Core\Core;
use Dotclear\Exception\DistribException;
use Dotclear\Helper\File\Path;
use Exception;

/**
 * Upgrade process (CLI).
 *
 * @ingroup  Distrib
 */
final class Prepend extends Core
{
    /**
     * Start Dotclear Distirb process.
     *
     * @param null|string $blog The blog id (not used)
     */
    public function startProcess(string $blog = null): void
    {
        if ('cli' != PHP_SAPI) {
            throw new DistribException('Not in CLI mode');
        }

        if (isset($_SERVER['argv'][1])) {
            $dc_conf = $_SERVER['argv'][1];
        } elseif (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
            $dc_conf = realpath($_SERVER['DOTCLEAR_CONFIG_PATH']);
        } else {
            $dc_conf = Path::implodeBase('dotclear.conf.php');
        }

        if (!is_file($dc_conf)) {
            throw new DistribException(sprintf('%s is not a file', $dc_conf));
        }

        $_SERVER['DOTCLEAR_CONFIG_PATH'] = $dc_conf;
        unset($dc_conf);

        // Check if configuration complete and app can run
        $this->config()->checkConfiguration();

        // Add top behaviors
        $this->setTopBehaviors();

        echo "Starting upgrade process\n";
        $this->con()->begin();

        try {
            $changes = (new Upgrade())->doUpgrade();
        } catch (Exception $e) {
            $this->con()->rollback();

            throw $e;
        }
        $this->con()->commit();
        echo -1 == $changes ? 'Nothing to upgrade' : 'Upgrade process successfully completed (' . $changes . "). \n";

        exit(0);
    }
}
