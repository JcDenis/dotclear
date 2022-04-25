<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\MaintenanceTask;

// Dotclear\Plugin\ImportExport\Admin\MaintenanceTask\Exportblog
use Dotclear\Plugin\ImportExport\Admin\Lib\Module\ExportFlat as BaseExportFlat;

/**
 * Export flat maintenance task of plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport Maintenance
 */
class ExportFlat extends BaseExportFlat
{
    /**
     * Set redirection URL of bakcup process.
     *
     * Bad hack to change redirection of dcExportFlat::process()
     *
     * @param string $id Task ID
     */
    public function setURL(string $id): void
    {
        $this->url = dotclear()->adminurl()->get('admin.plugin.Maintenance', ['task' => $id], '&');
    }
}
