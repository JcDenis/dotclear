<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskCountcomments
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Comments count maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskCountcomments extends MaintenanceTask
{
    protected $group = 'index';

    protected function init(): void
    {
        $this->task    = __('Count again comments and trackbacks');
        $this->success = __('Comments and trackback counted.');
        $this->error   = __('Failed to count comments and trackbacks.');

        $this->description = __('Count again comments and trackbacks allows to check their exact numbers. This operation can be useful when importing from another blog platform (or when migrating from dotclear 1 to dotclear 2).');
    }

    public function execute(): int|bool
    {
        $this->countAllComments();

        return true;
    }

    /**
     * Reinits nb_comment and nb_trackback in post table.
     */
    public function countAllComments(): void
    {
        // Comments
        $sql = new SelectStatement(__METHOD__);
        $sel = $sql
            ->column($sql->count('C.comment_id'))
            ->from(dotclear()->prefix . 'comment C')
            ->where([
                'C.post_id = P.post_id',
                'C.comment_trackback <> 1',
                'C.comment_status = 1',
            ])
            ->statement()
        ;

        $sql = UpdateStatement::init(__METHOD__)
            ->set('nb_comment = (' . $sel . ')')
            ->from(dotclear()->prefix . 'post P')
            ->update()
        ;

        // Trackback
        $sql = new SelectStatement(__METHOD__);
        $sel = $sql
            ->column($sql->count('C.comment_id'))
            ->from(dotclear()->prefix . 'comment C')
            ->where([
                'C.post_id = P.post_id',
                'C.comment_trackback = 1',
                'C.comment_status = 1',
            ])
            ->statement()
        ;

        $sql = UpdateStatement::init(__METHOD__)
            ->set('nb_trackback = (' . $sel . ')')
            ->from(dotclear()->prefix . 'post P')
            ->update()
        ;
    }
}
