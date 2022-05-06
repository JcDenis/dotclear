<?php
/**
 * @note Dotclear\Iconset\ThomasDaveluy\Admin\Prepend
 * @brief Dotclear Iconset class
 *
 * @ingroup  IconsetThomasDaveluy
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Iconset\ThomasDaveluy\Admin;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Modules\ModulePrepend;

class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        App::core()->behavior()->add('adminIconsetCombo', function (ArrayObject $iconsets): void {
            $iconsets['Thomas Daveluy'] = Path::real(Path::implode(__DIR__, '..'));
        });
    }
}
