<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme;

// Dotclear\Module\Theme\TraitModulesTheme
use Dotclear\App;
use Dotclear\Helper\File\Path;

/**
 * Theme modules methods.
 *
 * @ingroup  Module Theme
 */
trait TraitModulesTheme
{
    public function getModulesType(): string
    {
        return 'Theme';
    }

    public function getModulesPath(): array
    {
        $paths = App::core()->config()->get('theme_dirs');

        // If a theme directory is set for current blog, it will be added to the end of paths
        if (App::core()->blog()) {
            $path = trim((string) App::core()->blog()->settings()->get('system')->get('module_theme_dir'));
            if (!empty($path) && false !== ($dir = Path::real(str_starts_with('\\', $path) ? $path : Path::implodeRoot($path), true))) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    public function getStoreURL(): string
    {
        return (string) App::core()->blog()->settings()->get('system')->get('store_theme_url');
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return App::core()->config()->get('theme_official');
    }

    /**
     * Get current theme path.
     *
     * Return an array of theme and parent theme paths
     *
     * @param null|string $suffix Optionnal sub folder
     *
     * @return array List of theme path
     */
    public function getThemePath(?string $suffix = null): array
    {
        $suffix = $suffix ? '/' . $suffix : '';
        $path   = [];

        if (null !== App::core()->blog()) {
            $theme = $this->getModule((string) App::core()->blog()->settings()->get('system')->get('theme'));
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
