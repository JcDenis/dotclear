<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset;

/**
 * Iconset modules methods.
 *
 * \Dotclear\Module\Iconset\TraitModulesIconset
 *
 * @ingroup  Module Iconset
 */
trait TraitModulesIconset
{
    public function getModulesType(): string
    {
        return 'Iconset';
    }

    public function getModulesPath(): array
    {
        return dotclear()->config()->get('iconset_dirs');
    }

    public function getStoreURL(): string
    {
        return (string) dotclear()->blog()->settings()->get('system')->get('store_iconset_url');
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return dotclear()->config()->get('iconset_official');
    }
}
