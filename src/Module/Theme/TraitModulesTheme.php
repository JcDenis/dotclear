<?php
/**
 * @class Dotclear\Module\Theme\TraitModulesTheme
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme;

use Dotclear\Helper\File\Path;

trait TraitModulesTheme
{
    public function getModulesType(): string
    {
        return 'Theme';
    }

    public function getModulesPath(): array
    {
        $paths = dotclear()->config()->theme_dirs;

        # If a theme directory is set for current blog, it will be added to the end of paths
        if (dotclear()->blog()) {
            $path = (string) dotclear()->blog()->settings()->system->module_theme_dir;
            if (!empty($path) && false !== ($dir = Path::real(str_starts_with('\\', $path) ? $path : Path::implodeRoot($path), true))) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    public function getStoreURL(): string
    {
        return (string) dotclear()->blog()->settings()->system->store_theme_url;
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return dotclear()->config()->theme_official;
    }

    /**
     * Get current theme path
     *
     * Return an array of theme and parent theme paths
     *
     * @param   string|null     $suffix     Optionnal sub folder
     * @return  array                       List of theme path
     */
    public function getThemePath(?string $suffix = null): array
    {
        $suffix = $suffix ? '/' . $suffix : '';
        $path = [];

        if(dotclear()->blog() !== null) {
            $theme = $this->getModule((string) dotclear()->blog()->settings()->system->theme);
            if (!$theme) {
                $theme = $this->getModule('Berlin');
            }
            $path[$theme->id()] = $theme->root() . $suffix;

            if ($theme->parent()) {
                $parent = $this->getModule((string) $theme->parent());
                if ($parent) {
                    $theme = $this->getModule('Berlin');
                }
                $path[$parent->id()] = $parent->root() . $suffix;
            }
        }

        return $path;
    }
}
