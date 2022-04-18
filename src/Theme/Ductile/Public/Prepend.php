<?php
/**
 * @note Dotclear\Theme\Ductile\Public\Prepend
 * @brief Dotclear Theme class
 *
 * @ingroup  ThemeDuctile
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Ductile\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

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
