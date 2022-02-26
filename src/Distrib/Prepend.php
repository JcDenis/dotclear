<?php
/**
 * @brief Dotclear upgrade procedure (CLI)
 *
 * @package Dotclear
 * @subpackage Distrib
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Distrib;

use Dotclear\Exception\DistribException

use Dotclear\Core\Core;
use Dotclear\Distrib\Upgrade;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends Core
{
    protected $process = 'Distrib';

    protected function process(): void
    {
        if (PHP_SAPI != 'cli') {
            throw new DistribException('Not in CLI mode');
        }

        if (isset($_SERVER['argv'][1])) {
            $dc_conf = $_SERVER['argv'][1];
        } elseif (isset($_SERVER['DOTCLEAR_CONFIG_PATH'])) {
            $dc_conf = realpath($_SERVER['DOTCLEAR_CONFIG_PATH']);
        } else {
            $dc_conf = DOTCLEAR_ROOT_DIR . '/config.php';
        }

        if (!is_file($dc_conf)) {
            throw new DistribException(sprintf('%s is not a file', $dc_conf));
        }

        $_SERVER['DOTCLEAR_CONFIG_PATH'] = $dc_conf;
        unset($dc_conf);

        parent::process();

        echo "Starting upgrade process\n";
        $this->con()->begin();

        try {
            $changes = Upgrade::dotclearUpgrade();
        } catch (\Exception $e) {
            $this->con()->rollback();

            throw $e;
        }
        $this->con()->commit();
        echo 'Upgrade process successfully completed (' . $changes . "). \n";
        exit(0);
    }
}
