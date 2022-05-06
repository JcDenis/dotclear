<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\UserPref\Admin;

// Dotclear\Plugin\UserPref\Admin\Prepend
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin UserPref.
 *
 * @ingroup  Plugin UserPref
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        $this->addStandardMenu('System');
        $this->addStandardFavorites();
    }
}
