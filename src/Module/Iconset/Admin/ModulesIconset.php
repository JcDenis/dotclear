<?php
/**
 * @class Dotclear\Module\Iconset\Admin\ModulesIconset
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset\Admin;

use function Dotclear\core;

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Iconset\TraitModulesIconset;

class ModulesIconset extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesIconset;

    protected function register(): void
    {
        core()->adminurl->register(
            'admin.iconset',
            core()::ns('Dotclear', 'Module', 'Iconset', 'Admin', 'PageIconset')
        );
        core()->menu->register(
            'System',
            __('Iconset management'),
            'admin.iconset',
            'images/menu/no-icon.svg',
            core()->auth->isSuperAdmin()
        );
        core()->favs->register('iconsets', [
            'title'      => __('Iconsets management'),
            'url'        => core()->adminurl->get('admin.iconset'),
            'small-icon' => 'images/menu/no-icon.svg',
            'large-icon' => 'images/menu/no-icon.svg'
        ]);
    }

    public function getModulesURL(array $param = []): string
    {
        return core()->adminurl->get('admin.iconset', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return core()->adminurl->get('admin.iconset.' . $id, $param);
    }
/*
    public function displayModules(array $cols = ['name', 'version', 'desc'], array $actions = [], bool $nav_limit = false): AbstractModules
    {

        return $this;
    }
*/
}
