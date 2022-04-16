<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskSynchpostsmeta
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

use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

class MaintenanceTaskSynchpostsmeta extends MaintenanceTask
{
    protected $ajax  = true;
    protected $group = 'index';
    protected $limit = 100;
    protected $step_task;

    protected function init(): void
    {
        $this->name      = __('Entries metadata');
        $this->task      = __('Synchronize entries metadata');
        $this->step_task = __('Next');
        $this->step      = __('Synchronize entry %d to %d.');
        $this->success   = __('Entries metadata synchronize done.');
        $this->error     = __('Failed to synchronize entries metadata.');

        $this->description = __('Synchronize all entries metadata could be useful after importing content in your blog or do bad operation on database tables.');
    }

    public function execute(): int|bool
    {
        $this->code = $this->synchronizeAllPostsmeta($this->code, $this->limit);

        return $this->code ?: true;
    }

    public function task(): string
    {
        return $this->code ? $this->step_task : $this->task;
    }

    public function step(): ?string
    {
        return $this->code ? sprintf($this->step, $this->code - $this->limit, $this->code) : null;
    }

    public function success(): string
    {
        return $this->code ? sprintf($this->step, $this->code - $this->limit, $this->code) : $this->success;
    }

    protected function synchronizeAllPostsmeta(?int $start = null, ?int $limit = null): ?int
    {
        $sql = SelectStatement::init(__METHOD__)
            ->from(dotclear()->prefix . 'post');

        $sql_mid = SelectStatement::init(__METHOD__)
            ->from(dotclear()->prefix . 'meta');

        $sql_upd = UpdateStatement::init(__METHOD__)
            ->from(dotclear()->prefix . 'post');

        # Get number of posts
        $count = $sql
            ->column($sql->count('post_id'))
            ->select()
            ->fInt();

        # Get posts ids to update
        if (null !== $start && null !== $limit) {
            $sql->limit([$start, $limit]);
        }
        $rs = $sql
            ->column('post_id', true) # re-use statement
            ->select();

        # Update posts meta
        while ($rs->fetch()) {
            $rs_meta = $sql_mid
                ->columns([
                    'meta_id',
                    'meta_type',
                ], true)
                ->where('post_id = ' . $rs->fInt('post_id'), true)
                ->select();

            $meta = [];
            while ($rs_meta->fetch()) {
                $meta[$rs_meta->f('meta_type')][] = $rs_meta->f('meta_id');
            }

            $sql_upd
                ->set('post_meta = ' . $sql->quote(serialize($meta)), true)
                ->where('post_id = ' . $rs->fInt('post_id'), true)
                ->update();
        }
        dotclear()->blog()->triggerBlog();

        # Return next step
        return $start + $limit > $count ? null : $start + $limit;
    }
}
