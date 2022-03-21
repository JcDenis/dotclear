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

use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

class MaintenanceTaskSynchpostsmeta extends MaintenanceTask
{
    protected $ajax  = true;
    protected $group = 'index';
    protected $limit = 100;
    protected $step_task;

    protected function init()
    {
        $this->name      = __('Entries metadata');
        $this->task      = __('Synchronize entries metadata');
        $this->step_task = __('Next');
        $this->step      = __('Synchronize entry %d to %d.');
        $this->success   = __('Entries metadata synchronize done.');
        $this->error     = __('Failed to synchronize entries metadata.');

        $this->description = __('Synchronize all entries metadata could be useful after importing content in your blog or do bad operation on database tables.');
    }

    public function execute()
    {
        $this->code = $this->synchronizeAllPostsmeta($this->code, $this->limit);

        return $this->code ?: true;
    }

    public function task()
    {
        return $this->code ? $this->step_task : $this->task;
    }

    public function step()
    {
        return $this->code ? sprintf($this->step, $this->code - $this->limit, $this->code) : null;
    }

    public function success()
    {
        return $this->code ? sprintf($this->step, $this->code - $this->limit, $this->code) : $this->success;
    }

    protected function synchronizeAllPostsmeta($start = null, $limit = null)
    {
        // Get number of posts
        $count = dotclear()->con()->select('SELECT COUNT(post_id) FROM ' . dotclear()->prefix . 'post')->fInt();

        // Get posts ids to update
        $req_limit = $start !== null && $limit !== null ? dotclear()->con()->limit($start, $limit) : '';
        $rs        = dotclear()->con()->select('SELECT post_id FROM ' . dotclear()->prefix . 'post ' . $req_limit, true);

        // Update posts meta
        while ($rs->fetch()) {
            $rs_meta = dotclear()->con()->select('SELECT meta_id, meta_type FROM ' . dotclear()->prefix . 'meta WHERE post_id = ' . $rs->post_id . ' ');

            $meta = [];
            while ($rs_meta->fetch()) {
                $meta[$rs_meta->meta_type][] = $rs_meta->meta_id;
            }

            $cur            = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
            $cur->post_meta = serialize($meta);
            $cur->update('WHERE post_id = ' . $rs->post_id);
        }
        dotclear()->blog()->triggerBlog();

        // Return next step
        return $start + $limit > $count ? null : $start + $limit;
    }
}
