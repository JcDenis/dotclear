<?php
/**
 * @note Dotclear\Plugin\Pings\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @ingroup  PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Pings\Common\PingsCore;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new PingsCore();
    }
}
