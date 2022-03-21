<?php
/**
 * @class Dotclear\Module\TraitModulesPublic
 * @brief Dotclear Module Public specific methods
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

trait TraitModulesPublic
{
    /**
     * Not used on Public process
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    protected function loadModulesProcess(): void
    {
        return;
    }
    /**
     * Not used on Public process
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    protected function loadModuleProcess(string $id): void
    {
        return;
    }

    /**
     * Not used on Public process
     * @see Dotclear\Module\AbstractModules::loadModuleDefine()
     */
    protected function loadModuleDefineProcess(AbstractDefine $define): bool
    {
        return true;
    }
}
