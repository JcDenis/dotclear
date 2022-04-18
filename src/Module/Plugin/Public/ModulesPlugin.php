<?php
/**
 * @note Dotclear\Module\Plugin\Public\ModulesPlugin
 *
 * @ingroup  Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Public;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesPublic;
use Dotclear\Module\Plugin\TraitModulesPlugin;

class ModulesPlugin extends AbstractModules
{
    use TraitModulesPublic;
    use TraitModulesPlugin;
}
