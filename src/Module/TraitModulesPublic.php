<?php
/**
 * @class Dotclear\Module\TraitModulesPublic
 * @brief Dotclear Module Admin specific methods
 *
 * If exists, Module Config class must extends this class.
 * It provides a simple way to add an admin form to configure module.
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

trait TraitModulesPublic
{
    /**
     * Load modules Admin specifics
     * @see AbstractModules::loadModules()
     */
    protected function loadModulesProcess(): void
    {
        return;
    }

    /**
     * Check module permissions on Admin on load
     * @see AbstractModules::loadModuleDefine()
     */
    protected function loadModuleDefineProcess(AbstractDefine $define): bool
    {
        return true;
    }
}
