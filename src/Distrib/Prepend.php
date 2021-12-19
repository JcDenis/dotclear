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

use Dotclear\Exception;
use Dotclear\Exception\DistribException

use Dotclear\Core\Prepend as BasePrepend;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Distrib';

    public function __construct()
    {
        if (PHP_SAPI != 'cli') {
            throw new DistribException('Not in CLI mode');
        }

        if (isset($_SERVER['argv'][1])) {
            $dc_conf = $_SERVER['argv'][1];
        } elseif (isset($_SERVER['DC_RC_PATH'])) {
            $dc_conf = realpath($_SERVER['DC_RC_PATH']);
        } else {
            $dc_conf = DOTCLEAR_ROOT_DIR . '/config.php';
        }

        if (!is_file($dc_conf)) {
            throw new DistribException(sprintf('%s is not a file', $dc_conf));
        }

        $_SERVER['DC_RC_PATH'] = $dc_conf;
        unset($dc_conf);

        parent::__construct();

        echo "Starting upgrade process\n";
        $core->con->begin();

        try {
            $changes = Upgrade::dotclearUpgrade($core);
        } catch (Exception $e) {
            $core->con->rollback();

            throw $e;
        }
        $core->con->commit();
        echo 'Upgrade process successfully completed (' . $changes . "). \n";
        exit(0);
    }
}
?>
