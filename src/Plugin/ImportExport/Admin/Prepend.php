<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;


if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        # Menu and favs
        static::addStandardMenu('Plugins');
        static::addStandardFavorites();

        # ImportExport modules
        dotclear()->behavior()->add('importExportModules', function ($modules) {
            $ns = 'Dotclear\\Plugin\\ImportExport\\Lib\\Module\\';
            $modules['import'] = array_merge($modules['import'], [$ns . 'ImportFlat']);
            $modules['import'] = array_merge($modules['import'], [$ns . 'ImportFeed']);

            $modules['export'] = array_merge($modules['export'], [$ns . 'ExportFlat']);

            if (dotclear()->user()->isSuperAdmin()) {
                $modules['import'] = array_merge($modules['import'], [$ns . 'ImportDc1']);
                $modules['import'] = array_merge($modules['import'], [$ns . 'ImportWp']);
            }
        });

        # Maintenance task
        dotclear()->behavior()->add('dcMaintenanceInit', function ($maintenance) {
            $ns = 'Dotclear\\Plugin\\ImportExport\\Lib\\Task\\';
            $maintenance
                ->addTask($ns . 'ExportBlog')
                ->addTask($ns . 'ExportFull')
            ;
        });
    }
}
