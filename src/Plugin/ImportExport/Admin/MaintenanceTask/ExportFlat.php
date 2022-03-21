<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\MaintenanceTask\Exportblog
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\MaintenanceTask;

use Dotclear\Plugin\ImportExport\Admin\Lib\Module\ExportFlat as BaseExportFlat;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

class ExportFlat extends BaseExportFlat
{
    /**
     * Set redirection URL of bakcup process.
     *
     * Bad hack to change redirection of dcExportFlat::process()
     *
     * @param      string  $id     Task ID
     */
    public function setURL($id)
    {
        $this->url = dotclear()->adminurl()->get('admin.plugin.Maintenance', ['task' => $id], '&');
    }
}
