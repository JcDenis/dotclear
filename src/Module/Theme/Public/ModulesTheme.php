<?php
/**
 * @class Dotclear\Module\Theme\Public\ModulesTheme
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme\Public;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesPublic;
use Dotclear\Module\Theme\TraitModulesTheme;

class ModulesTheme extends AbstractModules
{
    use TraitModulesPublic, TraitModulesTheme;
}
