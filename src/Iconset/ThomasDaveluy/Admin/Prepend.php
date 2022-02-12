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

use Dotclear\File\Path;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        dotclear()->behaviors->add('adminIconsetCombo', [__CLASS__, 'adminIconsetCombo']);
    }

    public static function adminIconsetCombo(ArrayObject $iconsets)
    {
        $iconsets['Thomas Daveluy'] = Path::real(implode_path(__DIR__, '..'));
    }
}
