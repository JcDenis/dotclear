<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin;

// Dotclear\Plugin\ImportExport\Admin\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;

/**
 * Admin prepend for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Menu and favs
        $this->addStandardMenu('Plugins');
        $this->addStandardFavorites();

        // ImportExport modules
        App::core()->behavior()->add('importExportModules', function ($modules) {
            $ns                = __NAMESPACE__ . '\\Lib\\Module\\';
            $modules['import'] = array_merge($modules['import'], [$ns . 'ImportFlat']);
            $modules['import'] = array_merge($modules['import'], [$ns . 'ImportFeed']);

            $modules['export'] = array_merge($modules['export'], [$ns . 'ExportFlat']);
        });

        // Maintenance task
        App::core()->behavior()->add('dcMaintenanceInit', function ($maintenance) {
            $ns = __NAMESPACE__ . '\\MaintenanceTask\\';
            $maintenance
                ->addTask($ns . 'ExportBlog')
                ->addTask($ns . 'ExportFull')
            ;
        });
    }
}
