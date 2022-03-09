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
use Dotclear\Plugin\Maintenance\Admin\Maintenance;
use Dotclear\Html\Html;
use Dotclear\Html\XmlTag;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

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
     * @param      array      $get    cleaned $_GET
     * @param      array      $post   cleaned $_POST
     *
     * @throws     AdminException  (description)
     *
     * @return     xmlTag     XML representation of response.
     */
    public function step($get, $post)
    {
        if (!isset($post['task'])) {
            throw new AdminException('No task ID');
        }
        if (!isset($post['code'])) {
            throw new AdminException('No code ID');
        }

        $maintenance = new Maintenance();
        if (($task = $maintenance->getTask($post['task'])) === null) {
            throw new AdminException('Unknown task ID');
        }

        $task->code((int) $post['code']);
        if (($code = $task->execute()) === true) {
            $maintenance->setLog($task->id());
            $code = 0;
        }

        $rsp        = new XmlTag('step');
        $rsp->code  = $code;
        $rsp->title = Html::escapeHTML($task->success());

        return $rsp;
    }
}
