<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

// Dotclear\Theme\Ductile\Public\Prepend
use Dotclear\Modules\ModulePrepend;

/**
 * Public prepend for theme Ductile.
 *
 * @ingroup  Theme Ductile
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        new DuctileBehavior();
        new DuctileTemplate();
    }
}
