<?php
/**
 * @class Dotclear\Plugin\ImportExport\Lib\Task\Exportblog
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Lib\Task;

use Dotclear\Plugin\ImportExport\Lib\Module\ExportFlat as BaseExportFlat;
use Dotclear\Plugin\Maintenance\Lib\MaintenanceTask;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

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
