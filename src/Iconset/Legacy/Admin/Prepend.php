<?php
/**
 * @class Dotclear\Iconset\Legacy\Admin\Prepend
 * @brief Dotclear Iconset class
 *
 * @package Dotclear
 * @subpackage IconsetLegacy
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Iconset\Legacy\Admin;

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

    public static function checkModule(): bool
    {
        return true;
    }

    public static function loadModule(): void
    {
        dcCore()->behaviors->add('adminIconsetCombo', [__CLASS__, 'adminIconsetCombo']);
    }

    public static function adminIconsetCombo(ArrayObject $iconsets)
    {
        $iconsets['Legacy'] = Path::real(dcCore()::path(__DIR__, '..'));
    }
}
