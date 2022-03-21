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

use Dotclear\Helper\File\Path;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        dotclear()->behavior()->add('adminIconsetCombo', function (ArrayObject $iconsets): void {
            $iconsets['Legacy'] = Path::real(Path::implode(__DIR__, '..'));
        });
    }
}
