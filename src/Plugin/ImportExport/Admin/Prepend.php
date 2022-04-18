<?php
/**
 * @note Dotclear\Plugin\ImportExport\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public function loadModule(): void
    {
        // Menu and favs
        $this->addStandardMenu('Plugins');
        $this->addStandardFavorites();

        // ImportExport modules
        dotclear()->behavior()->add('importExportModules', function ($modules) {
            $ns                = __NAMESPACE__ . '\\Lib\\Module\\';
            $modules['import'] = array_merge($modules['import'], [$ns . 'ImportFlat']);
            $modules['import'] = array_merge($modules['import'], [$ns . 'ImportFeed']);

            $modules['export'] = array_merge($modules['export'], [$ns . 'ExportFlat']);

            if (dotclear()->user()->isSuperAdmin()) {
                $modules['import'] = array_merge($modules['import'], [$ns . 'ImportDc1']);
                $modules['import'] = array_merge($modules['import'], [$ns . 'ImportWp']);
            }
        });

        // Maintenance task
        dotclear()->behavior()->add('dcMaintenanceInit', function ($maintenance) {
            $ns = __NAMESPACE__ . '\\MaintenanceTask\\';
            $maintenance
                ->addTask($ns . 'ExportBlog')
                ->addTask($ns . 'ExportFull')
            ;
        });
    }
}
