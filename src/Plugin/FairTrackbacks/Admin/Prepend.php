<?php
/**
 * @class Dotclear\Plugin\FairTrackbacks\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginFairTrackbacks
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Admin;

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
        if (!defined('DC_FAIRTRACKBACKS_FORCE') || !DC_FAIRTRACKBACKS_FORCE) { // @phpstan-ignore-line
            dotclear()->behaviors->add('antispamInitFilters', function(ArrayObject $spamfilters): void {
                $spamfilters[] = 'Dotclear\\Plugin\\FairTrackbacks\\Lib\\FilterFairtrackbacks';
            });
        }
    }
}
