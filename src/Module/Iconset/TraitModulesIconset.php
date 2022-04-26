<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset;

// Dotclear\Module\Iconset\TraitModulesIconset
use Dotclear\App;

/**
 * Iconset modules methods.
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
        return App::core()->config()->get('iconset_dirs');
    }

    public function getStoreURL(): string
    {
        return (string) App::core()->blog()->settings()->get('system')->get('store_iconset_url');
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return App::core()->config()->get('iconset_official');
    }
}
