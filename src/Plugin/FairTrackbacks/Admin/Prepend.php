<?php
/**
 * @note Dotclear\Plugin\FairTrackbacks\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginFairTrackbacks
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\FairTrackbacks\Common\FilterFairtrackbacks;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        FilterFairtrackbacks::initFairTrackbacks();
    }
}
