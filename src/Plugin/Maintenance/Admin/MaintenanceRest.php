<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\MaintenanceRest
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin;

use Dotclear\Exception\AdminException;
use Dotclear\Plugin\Maintenance\Admin\Lib\Maintenance;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Html\XmlTag;

/**
@ingroup PLUGIN_MAINTENANCE
@nosubgrouping
@brief Maintenance plugin rest service class.

Serve maintenance methods via Dotclear's rest API
 */
class MaintenanceRest
{
    public function __construct()
    {
        dotclear()->rest()->addFunction('dcMaintenanceStep', [$this, 'step']);
    }

    /**
     * Serve method to do step by step task for maintenance.
     *
     * @param   array   $get    Cleaned $_GET
     * @param   array   $post   Cleaned $_POST
     *
     * @throws  AdminException  
     *
     * @return  XmlTag          XML representation of response.
     */
    public function step(array $get, array $post): XmlTag
    {
        if (!isset($post['task'])) {
            throw new AdminException('No task ID');
        }
        if (!isset($post['code'])) {
            throw new AdminException('No code ID');
        }

        $maintenance = new Maintenance();
        if (null === ($task = $maintenance->getTask($post['task']))) {
            throw new AdminException('Unknown task ID');
        }

        $task->code((int) $post['code']);
        if (true === ($code = $task->execute())) {
            $maintenance->setLog($task->id());
            $code = 0;
        }

        $rsp = new XmlTag('step');
        $rsp->insertAttr('code', $code);
        $rsp->insertAttr('title', Html::escapeHTML($task->success()));

        return $rsp;
    }
}
