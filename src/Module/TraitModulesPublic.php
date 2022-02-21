<?php
/**
 * @class Dotclear\Module\TraitModulesPublic
 * @brief Dotclear Module Admin specific methods
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Module\AbstractDefine;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

trait TraitModulesPublic
{
    /**
     * Load modules Admin specifics
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    protected function loadModulesProcess(): void
    {
        return;
    }
    /**
     * Load module Admin specifics
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    protected function loadModuleProcess(string $id): void
    {
        return;
    }

    /**
     * Check module permissions on Admin on load
     * @see Dotclear\Module\AbstractModules::loadModuleDefine()
     */
    protected function loadModuleDefineProcess(AbstractDefine $define): bool
    {
        return true;
    }
}
