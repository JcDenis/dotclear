<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Public;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesPublic;
use Dotclear\Module\Plugin\TraitModulesPlugin;

/**
 * Plugin modules public methods.
 *
 * \Dotclear\Module\Plugin\Public\ModulesPlugin
 *
 * @ingroup  Module Public Plugin
 */
class ModulesPlugin extends AbstractModules
{
    use TraitModulesPublic;
    use TraitModulesPlugin;
}
