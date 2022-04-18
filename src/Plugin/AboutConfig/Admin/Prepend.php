<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\AboutConfig\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

/**
 * Admin prepend for plugin AboutConfig.
 *
 * \Dotclear\Plugin\AboutConfig\Admin\Prepend
 *
 * @ingroup  Plugin AboutConfig
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        $this->addStandardMenu('System');
        $this->addStandardFavorites();
    }
}
