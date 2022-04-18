<?php
/**
 * @note Dotclear\Plugin\FairTrackbacks\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @ingroup  PluginFairTrackbacks
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\FairTrackbacks\Common\FilterFairtrackbacks;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        FilterFairtrackbacks::initFairTrackbacks();
    }
}
