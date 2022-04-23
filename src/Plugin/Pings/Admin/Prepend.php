<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pings\Admin;

// Dotclear\Plugin\Pings\Admin\Prepend
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;
use Dotclear\Plugin\Pings\Common\PingsCore;

/**
 * Admin prepend for plugin Pings.
 *
 * @ingroup  Plugin Pings
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        // Menu and favs
        $this->addStandardMenu('Blog', null);
        $this->addStandardFavorites(null);

        // Behaviors
        new PingsCore();
        new PingsBehavior();
    }

    public function installModule(): ?bool
    {
        dotclear()->blog()->settings()->get('pings')->put('pings_active', 1, 'boolean', 'Activate pings plugin', false, true);
        dotclear()->blog()->settings()->get('pings')->put('pings_auto', 0, 'boolean', 'Auto pings on 1st publication', false, true);
        dotclear()->blog()->settings()->get('pings')->put('pings_uris', ['Ping-o-Matic!' => 'http://rpc.pingomatic.com/'], 'array', 'Pings services URIs', false, true);

        return true;
    }
}
