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
        App::core()->behavior('adminBeforeAddImportExportModules')->add(function ($import, $export) {
            $ns = __NAMESPACE__ . '\\Lib\\Module\\';
            $import->add($ns . 'ImportFlat');
            $import->add($ns . 'ImportFeed');
            $export->add($ns . 'ExportFlat');
        });

        // Maintenance task
        App::core()->behavior('dcMaintenanceInit')->add(function ($maintenance) {
            $ns = __NAMESPACE__ . '\\MaintenanceTask\\';
            $maintenance
                ->addTask($ns . 'ExportBlog')
                ->addTask($ns . 'ExportFull')
            ;
        });
    }
}
