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

use Dotclear\Helper\File\Path;

trait TraitModulesPlugin
{
    public function getModulesType(): string
    {
        return 'Plugin';
    }

    public function getModulesPath(): array
    {
        $paths = dotclear()->config()->get('plugin_dirs');

        # If a plugin directory is set for current blog, it will be added to the end of paths
        if (dotclear()->blog()) {
            $path = trim((string) dotclear()->blog()->settings()->get('system')->get('module_plugin_dir'));
            if (!empty($path) && false !== ($dir = Path::real(str_starts_with('\\', $path) ? $path : Path::implodeRoot($path), true))) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    public function getStoreURL(): string
    {
        return (string) dotclear()->blog()->settings()->get('system')->get('store_plugin_url');
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return dotclear()->config()->get('plugin_official');
    }
}
