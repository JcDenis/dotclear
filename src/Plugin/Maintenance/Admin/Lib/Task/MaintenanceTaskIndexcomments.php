<?php
/**
 * @class Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskIndexcomments
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

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class MaintenanceTaskIndexcomments extends MaintenanceTask
{
    protected $ajax  = true;
    protected $group = 'index';
    protected $limit = 500;
    protected $step_task;

    protected function init()
    {
        $this->name      = __('Search engine index');
        $this->task      = __('Index all comments for search engine');
        $this->step_task = __('Next');
        $this->step      = __('Indexing comment %d to %d.');
        $this->success   = __('Comments index done.');
        $this->error     = __('Failed to index comments.');

        $this->description = __('Index all comments and trackbacks in search engine index. This operation is necessary, after importing content in your blog, to use internal search engine, on public and private pages.');
    }

    public function execute()
    {
        $this->code = $this->indexAllComments($this->code, $this->limit);

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
     * Recreates comments search engine index.
     *
     * @param  int|null     $start  The start comment index
     * @param  int|null     $limit  The limit of comment to index
     *
     * @return int|null     Sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllComments(?int $start = null, ?int $limit = null): ?int
    {
        $strReq = 'SELECT COUNT(comment_id) ' .
        'FROM ' . dotclear()->prefix . 'comment';
        $count = dotclear()->con()->select($strReq)->fInt();

        $strReq = 'SELECT comment_id, comment_content ' .
        'FROM ' . dotclear()->prefix . 'comment ';

        if ($start !== null && $limit !== null) {
            $strReq .= dotclear()->con()->limit($start, $limit);
        }

        $rs = dotclear()->con()->select($strReq);

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');

        while ($rs->fetch()) {
            $cur->comment_words = implode(' ', Text::splitWords($rs->comment_content));
            $cur->update('WHERE comment_id = ' . (int) $rs->comment_id);
            $cur->clean();
        }

        if ($start + $limit > $count) {
            return null;
        }

        return $start + $limit;
    }
}
