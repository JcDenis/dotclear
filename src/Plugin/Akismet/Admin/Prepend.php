<?php
/**
 * @class Dotclear\Plugin\Akismet\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAkismet
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        dotclear()->blog->settings->addNamespace('akismet');

        dotclear()->behavior()->add('antispamInitFilters', function(ArrayObject $spamfilters): void {
            $spamfilters[] = 'Dotclear\\Plugin\\Akismet\\Lib\\FilterAkismet';
        });
    }
}
