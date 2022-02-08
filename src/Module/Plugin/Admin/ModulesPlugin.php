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

use function Dotclear\core;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Plugin\TraitModulesPlugin;

class ModulesPlugin extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesPlugin;

    protected function register(): void
    {
        core()->adminurl->register(
            'admin.plugins',
            core()::ns('Dotclear', 'Module', 'Plugin', 'Admin', 'PagePlugin')
        );
        core()->menu->register(
            'System',
            __('Plugins management'),
            'admin.plugins',
            ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            core()->auth->isSuperAdmin()
        );
        core()->favs->register('plugins', [
            'title'      => __('Plugins management'),
            'url'        => core()->adminurl->get('admin.plugins'),
            'small-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            'large-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg']
        ]);
    }

    public function getModulesURL(array $param = []): string
    {
        return core()->adminurl->get('admin.plugins', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return core()->adminurl->get('admin.plugin.' . $id, $param);
    }
}
