<?php
/**
 * @class Dotclear\Log\TraitLog
 * @brief Dotclear trait Log
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

use Dotclear\Utils\Log;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitLog
{
    /** @var    Log   Log instance */
    private $log;

    /**
     * Get instance
     *
     * @return  Log   Log instance
     */
    public function log(): Log
    {
        if (!($this->log instanceof Log)) {
            $this->log = new Log();
        }

        return $this->log;
    }
}
