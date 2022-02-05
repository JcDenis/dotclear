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

use Dotclear\File\Path;

trait TraitModulesTheme
{
    public function getModulesType(): string
    {
        return 'Theme';
    }

    public function getModulesPath(): array
    {
        return explode(PATH_SEPARATOR, DOTCLEAR_THEME_DIR);
    }

    public function getStoreURL(): string
    {
        return (string) dcCore()->blog->settings->system->store_theme_url;
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return explode(',', DOTCLEAR_THEME_OFFICIAL);
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

        if(dcCore()->blog !== null) {
            dcCore()->blog->settings->addNamespace('system');
            $theme = $this->getModule((string) dcCore()->blog->settings->system->theme);
            if (!$theme) {
                $theme = $this->getModule('Blowup');
            }
            $path[$theme->id()] = $theme->root() . $suffix;

            if ($theme->parent()) {
                $parent = $this->getModule((string) $theme->parent());
                if ($parent) {
                    $theme = $this->getModule('Blowup');
                }
                $path[$parent->id()] = $parent->root() . $suffix;
            }
        }

        return $path;
    }
}
