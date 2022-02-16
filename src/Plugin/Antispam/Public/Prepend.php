<?php
/**
 * @class Dotclear\Plugin\Antispam\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Plugin\Antispam\Lib\Antispam;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        # Settings
        dotclear()->blog()->settings()->addNamespace('antispam');

        # Url
        $class = 'Dotclear\\Plugin\\Antispam\\Lib\\AntispamUrl';
        dotclear()->url()->register('spamfeed', 'spamfeed', '^spamfeed/(.+)$', [$class, 'spamFeed']);
        dotclear()->url()->register('hamfeed', 'hamfeed', '^hamfeed/(.+)$', [$class, 'hamFeed']);

        $class = 'Dotclear\\Plugin\\Antispam\\Lib\\Antispam';
        dotclear()->behavior()->add('publicBeforeCommentCreate', [$class, 'isSpam']);
        dotclear()->behavior()->add('publicBeforeTrackbackCreate', [$class, 'isSpam']);
        dotclear()->behavior()->add('publicBeforeDocument', [$class, 'purgeOldSpam']);
    }
}
