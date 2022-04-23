<?php
/**
 * @note
 * @brief Dotclear Plugin class
 *
 * @ingroup  PluginPings
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Public;

// Dotclear\Plugin\Pings\Public\Prepend
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Pings\Common\PingsCore;

/**
 * Public prepend for plugin Pings.
 *
 * @ingroup  Plugin Pings
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new PingsCore();
    }
}
