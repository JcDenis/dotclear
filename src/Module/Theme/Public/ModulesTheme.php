<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme\Public;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesPublic;
use Dotclear\Module\Theme\TraitModulesTheme;

/**
 * Theme modules public methods.
 *
 * \Dotclear\Module\Theme\Public\ModulesTheme
 *
 * @ingroup  Module Public Theme
 */
class ModulesTheme extends AbstractModules
{
    use TraitModulesPublic;
    use TraitModulesTheme;
}
