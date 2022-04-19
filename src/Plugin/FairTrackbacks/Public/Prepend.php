<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\FairTrackbacks\Common\FilterFairtrackbacks;

/**
 * Public prepend for plugin FairTrackbacks.
 *
 * \Dotclear\Plugin\FairTrackbacks\Public\Prepend
 *
 * @ingroup  Plugin FairTrackbacks
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        FilterFairtrackbacks::initFairTrackbacks();
    }
}
