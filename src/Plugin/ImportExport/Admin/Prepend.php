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
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

/**
 * Admin prepend for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

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

            if (App::core()->user()->isSuperAdmin()) {
                $modules['import'] = array_merge($modules['import'], [$ns . 'ImportDc1']);
            }
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
