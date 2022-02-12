<?php
/**
 * @class Dotclear\Module\Iconset\TraitModulesIconset
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset;

trait TraitModulesIconset
{
    public function getModulesType(): string
    {
        return 'Iconset';
    }

    public function getModulesPath(): array
    {
        return explode(PATH_SEPARATOR, dotclear()->config()->iconset_dir);
    }

    public function getStoreURL(): string
    {
        return (string) dotclear()->blog->settings->system->store_iconset_url;
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return explode(',', dotclear()->config()->iconset_official);
    }
}
