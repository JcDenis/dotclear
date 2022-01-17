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
        $paths = explode(PATH_SEPARATOR, DOTCLEAR_THEME_DIR);
        if($this->core->blog !== null) {
            $this->core->blog->settings->addNamespace('system');
            if ('' != ($blog_path = $this->core->blog->settings->system->themes_path)) {
                array_unshift($paths, Path::fullFromRoot($blog_path, DOTCLEAR_OTHER_DIR));
            }
        }

        return $paths;
    }

    public function getStoreURL(): string
    {
        return (string) $this->core->blog->settings->system->store_theme_url;
    }

    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    public function getDistributedModules(): array
    {
        return explode(',', DOTCLEAR_THEME_OFFICIAL);
    }
}
