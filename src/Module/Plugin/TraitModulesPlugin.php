<?php
/**
 * @class Dotclear\Module\Plugin\TraitModulesPlugin
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin;

use function Dotclear\core;

trait TraitModulesPlugin
{
    public function getModulesType(): string
    {
        return 'Plugin';
    }

    public function getModulesPath(): array
    {
        return explode(PATH_SEPARATOR, DOTCLEAR_PLUGIN_DIR);
    }

    public function getStoreURL(): string
    {
        return (string) core()->blog->settings->system->store_plugin_url;
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return explode(',', DOTCLEAR_PLUGIN_OFFICIAL);
    }
}
