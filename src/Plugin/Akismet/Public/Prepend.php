<?php
/**
 * @class Dotclear\Plugin\Akismet\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginAkismet
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Public;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        dotclear()->blog()->settings->addNamespace('akismet');

        dotclear()->behavior()->add('antispamInitFilters', function(ArrayObject $spamfilters): void {
            $spamfilters[] = 'Dotclear\\Plugin\\Akismet\\Lib\\FilterAskimet';
        });
    }
}
