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

use Dotclear\File\Path;

trait TraitModulesPlugin
{
    public function getModulesType(): string
    {
        return 'Plugin';
    }

    public function getModulesPath(): array
    {
        $paths = explode(PATH_SEPARATOR, dotclear()->config()->plugin_dir);

        # If a plugin directory is set for current blog, it will be added to the end of paths
        if (dotclear()->blog()) {
            dotclear()->blog()->settings()->addNamespace('system');
            $path = trim((string) dotclear()->blog()->settings()->system->module_plugin_dir);
            if (!empty($path) && false !== ($dir = Path::real(strpos('\\', $path) === 0 ? $path : root_path($path), true))) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    public function getStoreURL(): string
    {
        return (string) dotclear()->blog()->settings()->system->store_plugin_url;
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return explode(',', dotclear()->config()->plugin_official);
    }
}
