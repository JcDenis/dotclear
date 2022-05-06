<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\FairTrackbacks\Admin;

// Dotclear\Plugin\FairTrackbacks\Admin\Prepend
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\FairTrackbacks\Common\FilterFairtrackbacks;

/**
 * Admin prepend for plugin FairTrackbacks.
 *
 * @ingroup  Plugin FairTrackbacks
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        FilterFairtrackbacks::initFairTrackbacks();
    }
}
