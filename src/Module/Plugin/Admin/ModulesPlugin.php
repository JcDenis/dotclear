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

    protected function register(): void
    {
        $this->core->adminurl->register(
            'admin.plugins',
            $this->core::ns('Dotclear', 'Module', 'Plugin', 'Admin', 'PagePlugin')
        );
        $this->core->menu->register(
            'System',
            __('Plugins management'),
            'admin.plugins',
            'images/menu/plugins.png',
            $this->core->auth->isSuperAdmin()
        );
        $this->core->favs->register('plugins', [
            'title'      => __('Plugins management'),
            'url'        => $this->core->adminurl->get('admin.plugins'),
            'small-icon' => 'images/menu/plugins.png',
            'large-icon' => 'images/menu/plugins-b.png'
        ]);
    }

    public function getModulesURL(array $param = []): string
    {
        return $this->core->adminurl->get('admin.plugins', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return $this->core->adminurl->get('admin.plugin.' . $id, $param);
    }
}
