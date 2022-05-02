<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset\Admin;

// Dotclear\Module\Iconset\Admin\ModulesIconset
use Dotclear\App;
use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;

/**
 * Iconset modules admin methods.
 *
 * @ingroup  Module Admin Iconset
 */
class ModulesIconset extends AbstractModules
{
    use TraitModulesAdmin;

    protected function register(): bool
    {
        App::core()->adminurl()->register(
            'admin.iconset',
            'Dotclear\\Module\\Iconset\\Admin\\HandlerIconset'
        );
        App::core()->summary()->register(
            'System',
            __('Iconset management'),
            'admin.iconset',
            'images/menu/no-icon.svg',
            App::core()->user()->isSuperAdmin()
        );
        App::core()->favorite()->register('iconsets', [
            'title'      => __('Iconsets management'),
            'url'        => App::core()->adminurl()->get('admin.iconset'),
            'small-icon' => 'images/menu/no-icon.svg',
            'large-icon' => 'images/menu/no-icon.svg',
        ]);

        return App::core()->adminurl()->is('admin.iconset');
    }
}
