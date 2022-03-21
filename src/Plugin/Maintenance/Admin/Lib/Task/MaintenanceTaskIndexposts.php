<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskIndexposts
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
use Dotclear\Helper\Text;

class MaintenanceTaskIndexposts extends MaintenanceTask
{
    protected $ajax  = true;
    protected $group = 'index';
    protected $limit = 500;
    protected $step_task;

    protected function init()
    {
        $this->name      = __('Search engine index');
        $this->task      = __('Index all entries for search engine');
        $this->step_task = __('Next');
        $this->step      = __('Indexing entry %d to %d.');
        $this->success   = __('Entries index done.');
        $this->error     = __('Failed to index entries.');

        $this->description = __('Index all entries in search engine index. This operation is necessary, after importing content in your blog, to use internal search engine, on public and private pages.');
    }

    public function execute()
    {
        $this->code = $this->indexAllPosts($this->code, $this->limit);

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

    /**
     * Recreates entries search engine index.
     *
     * @param   int|null    $start  The start entry index
     * @param   int|null    $limit  The limit of entry to index
     *
     * @return  int|null    Sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllPosts(?int $start = null, ?int $limit = null): ?int
    {
        $strReq = 'SELECT COUNT(post_id) ' .
        'FROM ' . dotclear()->prefix . 'post';
        $count = dotclear()->con()->select($strReq)->fInt();

        $strReq = 'SELECT post_id, post_title, post_excerpt_xhtml, post_content_xhtml ' .
        'FROM ' . dotclear()->prefix . 'post ';

        if ($start !== null && $limit !== null) {
            $strReq .= dotclear()->con()->limit($start, $limit);
        }

        $rs = dotclear()->con()->select($strReq, true);

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');

        while ($rs->fetch()) {
            $words = $rs->post_title . ' ' . $rs->post_excerpt_xhtml . ' ' .
            $rs->post_content_xhtml;

            $cur->post_words = implode(' ', Text::splitWords($words));
            $cur->update('WHERE post_id = ' . (int) $rs->post_id);
            $cur->clean();
        }

        if ($start + $limit > $count) {
            return null;
        }

        return $start + $limit;
    }
}
