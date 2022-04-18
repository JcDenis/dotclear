<?php
/**
 * @note Dotclear\Plugin\UserPref\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PlugniUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        $this->addStandardMenu('System');
        $this->addStandardFavorites();
    }
}
