<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskCountcomments
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginMaintenance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class MaintenanceTaskCountcomments extends MaintenanceTask
{
    protected $group = 'index';

    protected function init()
    {
        $this->task    = __('Count again comments and trackbacks');
        $this->success = __('Comments and trackback counted.');
        $this->error   = __('Failed to count comments and trackbacks.');

        $this->description = __('Count again comments and trackbacks allows to check their exact numbers. This operation can be useful when importing from another blog platform (or when migrating from dotclear 1 to dotclear 2).');
    }

    public function execute()
    {
        $this->countAllComments();

        return true;
    }

    /**
     * Reinits nb_comment and nb_trackback in post table.
     */
    public function countAllComments(): void
    {
        $updCommentReq = 'UPDATE ' . dotclear()->prefix . 'post P ' .
        'SET nb_comment = (' .
        'SELECT COUNT(C.comment_id) from ' . dotclear()->prefix . 'comment C ' .
            'WHERE C.post_id = P.post_id AND C.comment_trackback <> 1 ' .
            'AND C.comment_status = 1 ' .
            ')';
        $updTrackbackReq = 'UPDATE ' . dotclear()->prefix . 'post P ' .
        'SET nb_trackback = (' .
        'SELECT COUNT(C.comment_id) from ' . dotclear()->prefix . 'comment C ' .
            'WHERE C.post_id = P.post_id AND C.comment_trackback = 1 ' .
            'AND C.comment_status = 1 ' .
            ')';
        dotclear()->con()->execute($updCommentReq);
        dotclear()->con()->execute($updTrackbackReq);
    }
}
