<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

// Dotclear\Module\TraitModulesPublic

/**
 * Dotclear Module Public specific methods.
 *
 * @ingroup  Module
 */
trait TraitModulesPublic
{
    /** This method is not used on Public process. */
    protected function loadModulesProcess(): void
    {
    }

    /** This method is not used on Public process. */
    protected function loadModuleProcess(string $id): void
    {
    }

    /** This method is not used on Public process. */
    protected function loadModuleDefineProcess(ModuleDefine $define): bool
    {
        return true;
    }
}
