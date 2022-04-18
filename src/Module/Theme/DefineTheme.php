<?php
/**
 * @note Dotclear\Module\Theme\DefineTheme
 *
 * @ingroup  Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme;

use Dotclear\Module\AbstractDefine;

class DefineTheme extends AbstractDefine
{
    protected $type = 'Theme';

    // Force permissions on Theme
    public function permissions(): ?string
    {
        return 'Admin';
    }
}
