<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin\Admin;

// Dotclear\Module\Plugin\Admin\ModulesPlugin
use Dotclear\App;
use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;

/**
 * Plugin modules admin methods.
 *
 * @ingroup  Module Admin Plugin
 */
class ModulesPlugin extends AbstractModules
{
    use TraitModulesAdmin;

    protected function register(): bool
    {
        App::core()->adminurl()->register(
            'admin.plugin',
            'Dotclear\\Module\\Plugin\\Admin\\HandlerPlugin'
        );
        App::core()->summary()->register(
            'System',
            __('Plugins management'),
            'admin.plugin',
            ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            App::core()->user()->isSuperAdmin()
        );
        App::core()->favorite()->register('plugins', [
            'title'      => __('Plugins management'),
            'url'        => App::core()->adminurl()->get('admin.plugin'),
            'small-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            'large-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
        ]);

        return App::core()->adminurl()->is('admin.plugin');
    }
}
