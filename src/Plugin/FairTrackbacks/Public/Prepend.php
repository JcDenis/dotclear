<?php
/**
 * @class Dotclear\Plugin\FairTrackbacks\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginFairTrackbacks
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Public;

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
        if (defined('DC_FAIRTRACKBACKS_FORCE') && DC_FAIRTRACKBACKS_FORCE) { // @phpstan-ignore-line
            dotclear()->behavior()->add('antispamInitFilters', function(ArrayObject $spamfilters): void {
                $spamfilters[] = 'Dotclear\\Plugin\\FairTrackbacks\\Lib\\FilterFairtrackbacks';
            });
        }
    }
}
