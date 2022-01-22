<?php
/**
 * @class Dotclear\Iconset\ThomasDaveluy\Admin\Prepend
 * @brief Dotclear Iconset class
 *
 * @package Dotclear
 * @subpackage IconsetThomasDaveluy
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Iconset\ThomasDaveluy\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Core\Core;

use Dotclear\File\Path;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function checkModule(Core $core): bool
    {
        return true;
    }

    public static function loadModule(Core $core): void
    {
        $core->behaviors->add('adminIconsetCombo', [__CLASS__, 'adminIconsetCombo']);
    }

    public static function adminIconsetCombo(Core $core, ArrayObject $iconsets)
    {
        $iconsets['Thomas Daveluy'] = Path::real(Core::path(dirname(__FILE__), '..'));
    }
}
