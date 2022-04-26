<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin;

// Dotclear\Module\Plugin\TraitModulesPlugin
use Dotclear\App;
use Dotclear\Helper\File\Path;

/**
 * Plugin modules methods.
 *
 * @ingroup  Module Plugin
 */
trait TraitModulesPlugin
{
    public function getModulesType(): string
    {
        return 'Plugin';
    }

    public function getModulesPath(): array
    {
        $paths = App::core()->config()->get('plugin_dirs');

        // If a plugin directory is set for current blog, it will be added to the end of paths
        if (App::core()->blog()) {
            $path = trim((string) App::core()->blog()->settings()->get('system')->get('module_plugin_dir'));
            if (!empty($path) && false !== ($dir = Path::real(str_starts_with('\\', $path) ? $path : Path::implodeRoot($path), true))) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    public function getStoreURL(): string
    {
        return (string) App::core()->blog()->settings()->get('system')->get('store_plugin_url');
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return App::core()->config()->get('plugin_official');
    }
}
