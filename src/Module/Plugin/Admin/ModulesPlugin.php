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
use Dotclear\Module\Plugin\TraitModulesPlugin;

/**
 * Plugin modules admin methods.
 *
 * @ingroup  Module Admin Plugin
 */
class ModulesPlugin extends AbstractModules
{
    use TraitModulesAdmin;
    use TraitModulesPlugin;

    protected function register(): bool
    {
        App::core()->adminurl()->register(
            'admin.plugins',
            'Dotclear\\Module\\Plugin\\Admin\\HandlerPlugin'
        );
        App::core()->summary()->register(
            'System',
            __('Plugins management'),
            'admin.plugins',
            ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            App::core()->user()->isSuperAdmin()
        );
        App::core()->favorite()->register('plugins', [
            'title'      => __('Plugins management'),
            'url'        => App::core()->adminurl()->get('admin.plugins'),
            'small-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
            'large-icon' => ['images/menu/plugins.svg', 'images/menu/plugins-dark.svg'],
        ]);

        return App::core()->adminurl()->is('admin.plugins');
    }

    public function getModulesURL(array $param = []): string
    {
        return App::core()->adminurl()->get('admin.plugins', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return App::core()->adminurl()->get('admin.plugin.' . $id, $param);
    }
}
