<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

/**
 * Dotclear Module Public specific methods.
 *
 * \Dotclear\Module\TraitModulesPublic
 *
 * @ingroup  Module Public
 */
trait TraitModulesPublic
{
    /**
     * Not used on Public process.
     *
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    protected function loadModulesProcess(): void
    {
    }

    /**
     * Not used on Public process.
     *
     * @see Dotclear\Module\AbstractModules::loadModules()
     */
    protected function loadModuleProcess(string $id): void
    {
    }

    /**
     * Not used on Public process.
     *
     * @see Dotclear\Module\AbstractModules::loadModuleDefine()
     */
    protected function loadModuleDefineProcess(AbstractDefine $define): bool
    {
        return true;
    }
}
