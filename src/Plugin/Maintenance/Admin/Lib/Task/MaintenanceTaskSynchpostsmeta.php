<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskSynchpostsmeta
use Dotclear\App;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Posts meta maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
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
        $sql = new SelectStatement();
        $sql->from(App::core()->prefix() . 'post');

        $sql_mid = new SelectStatement();
        $sql_mid->from(App::core()->prefix() . 'meta');

        $sql_upd = new UpdateStatement();
        $sql_upd->from(App::core()->prefix() . 'post');

        // Get number of posts
        $sql->column($sql->count('post_id'));
        $count = $sql->select()->integer();

        // Get posts ids to update
        if (null !== $start && null !== $limit) {
            $sql->limit([$start, $limit]);
        }

        $sql->column('post_id', true); // re-use statement
        $record = $sql->select();

        // Update posts meta
        while ($record->fetch()) {
            $sql_mid->columns([
                'meta_id',
                'meta_type',
            ], true);
            $sql_mid->where('post_id = ' . $record->integer('post_id'), true);
            $record_meta = $sql_mid->select();

            $meta = [];
            while ($record_meta->fetch()) {
                $meta[$record_meta->field('meta_type')][] = $record_meta->field('meta_id');
            }

            $sql_upd->set('post_meta = ' . $sql->quote(serialize($meta)), true);
            $sql_upd->where('post_id = ' . $record->integer('post_id'), true);
            $sql_upd->update();
        }
        App::core()->blog()->triggerBlog();

        // Return next step
        return $start + $limit > $count ? null : $start + $limit;
    }
}
