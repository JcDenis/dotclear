<?php
/**
 * @brief maintenance, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\maintenance\Task;

use dcBlog;
use dcCore;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Helper\Text;
use Dotclear\Plugin\maintenance\MaintenanceTask;

class IndexComments extends MaintenanceTask
{
    protected $id = 'dcMaintenanceIndexcomments';

    /**
     * Use ajax
     *
     * Is task use maintenance ajax script for steps process.
     *
     * @return    boolean    Use ajax
     */
    protected $ajax = true;

    /**
     * Task group container
     *
     * @var string
     */
    protected $group = 'index';

    /**
     * Number of comments to process by step
     *
     * @var int
     */
    protected $limit = 500;

    /**
     * Next step label
     *
     * @var string
     */
    protected $step_task;

    /**
     * Initialize task object.
     */
    protected function init(): void
    {
        $this->name      = __('Search engine index');
        $this->task      = __('Index all comments for search engine');
        $this->step_task = __('Next');
        $this->step      = __('Indexing comment %d to %d.');
        $this->success   = __('Comments index done.');
        $this->error     = __('Failed to index comments.');

        $this->description = __('Index all comments and trackbacks in search engine index. This operation is necessary, after importing content in your blog, to use internal search engine, on public and private pages.');
    }

    /**
     * Execute task.
     *
     * @return    bool|int
     *    - FALSE on error,
     *    - TRUE if task is finished
     *    - INT if task required a next step
     */
    public function execute()
    {
        $this->code = self::indexAllComments($this->code, $this->limit);

        return $this->code ?: true;
    }

    /**
     * Get task message.
     *
     * This message is used on form button.
     *
     * @return    string    Message
     */
    public function task(): string
    {
        return $this->code ? $this->step_task : $this->task;
    }

    /**
     * Get step message.
     *
     * This message is displayed during task step execution.
     *
     * @return    mixed     Message or null
     */
    public function step()
    {
        return $this->code ? sprintf($this->step, $this->code - $this->limit, $this->code) : null;
    }

    /**
     * Get success message.
     *
     * This message is displayed when task is accomplished.
     *
     * @return    string    Message or null
     */
    public function success(): string
    {
        return $this->code ? sprintf($this->step, $this->code - $this->limit, $this->code) : $this->success;
    }

    /**
     * Recreates comments search engine index.
     *
     * @param   null|int    $start  The start comment index
     * @param   null|int    $limit  The limit of comment to index
     *
     * @return  null|int    sum of <var>$start</var> and <var>$limit</var>
     */
    public static function indexAllComments(?int $start = null, ?int $limit = null): ?int
    {
        $sql = new SelectStatement();
        $rs = $sql
            ->from(dcCore::app()->prefix . dcBlog::COMMENT_TABLE_NAME)
            ->column($sql->count('comment_id'))
            ->select();

        $count = is_null($rs) || !is_numeric($rs->f(0)) ? 0 : (int) $rs->f(0);

        $sql = new SelectStatement();
        $sql
            ->from(dcCore::app()->prefix . dcBlog::COMMENT_TABLE_NAME)
            ->columns([
                'comment_id',
                'comment_content',
            ]);

        if ($start !== null && $limit !== null) {
            $sql->limit([$start, $limit]);
        }

        $rs = $sql->select();
        if (is_null($rs) || $rs->isEmpty()) {
            return null;
        }

        $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcBlog::COMMENT_TABLE_NAME);

        while ($rs->fetch()) {
            $cur->comment_words = implode(' ', Text::splitWords($rs->comment_content));
            $cur->update('WHERE comment_id = ' . (int) $rs->comment_id);
            $cur->clean();
        }

        return $start + $limit > $count ? null : $start + $limit;
    }
}
