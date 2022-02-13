<?php
/**
 * @class Dotclear\Module\Plugin\Admin\ModulesPlugin
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Admin;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Plugin\TraitModulesPlugin;

class ModulesPlugin extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesPlugin;

    protected function register(): bool
    {
        dotclear()->adminurl->register(
            'admin.plugins',
            root_ns('Module', 'Plugin', 'Admin', 'PagePlugin')
        );
        dotclear()->menu->register(
            'System',
            __('Plugins management'),
            'admin.plugins',
            ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            dotclear()->auth()->isSuperAdmin()
        );
        dotclear()->favs->register('plugins', [
            'title'      => __('Plugins management'),
            'url'        => dotclear()->adminurl->get('admin.plugins'),
            'small-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            'large-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg']
        ]);

        return dotclear()->adminurl->called() == 'admin.plugins';
    }

    public function getModulesURL(array $param = []): string
    {
        return dotclear()->adminurl->get('admin.plugins', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return dotclear()->adminurl->get('admin.plugin.' . $id, $param);
    }
}
