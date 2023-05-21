<?php
/**
 * @brief akismet, an antispam filter plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\akismet;

use dcCore;
use dcNsProcess;

class Prepend extends dcNsProcess
{
    public static function init(): bool
    {
        return (static::$init = defined('DC_RC_PATH'));
    }

    public static function process(): bool
    {
        if (static::$init) {
            dcCore::app()->spamfilters[] = AntispamFilterAkismet::class;
        }

        return static::$init;
    }
}
