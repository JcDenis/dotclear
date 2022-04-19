<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

/**
 * Public prepend for theme Ductile.
 *
 * \Dotclear\Theme\Ductile\Public\Prepend
 *
 * @ingroup  Theme Ductile
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        new DuctileBehavior();
        new DuctileTemplate();
    }
}
