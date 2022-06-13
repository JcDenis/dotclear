<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Maintenance\Admin\Lib\Task;

// Dotclear\Plugin\Maintenance\Admin\Lib\Task\MaintenanceTaskIndexcomments
use Dotclear\App;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Text;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

/**
 * Comments index maintenance Task.
 *
 * @ingroup  Plugin Maintenance Task
 */
class MaintenanceTaskIndexcomments extends MaintenanceTask
{
    protected $ajax  = true;
    protected $group = 'index';
    protected $limit = 500;
    protected $step_task;

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

    public function execute(): int|bool
    {
        $this->code = $this->indexAllComments($this->code, $this->limit);

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

    /**
     * Recreates comments search engine index.
     *
     * @param null|int $start The start comment index
     * @param null|int $limit The limit of comment to index
     *
     * @return null|int Sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllComments(?int $start = null, ?int $limit = null): ?int
    {
        $sql   = new SelectStatement();
        $sql->column($sql->count('comment_id'));
        $sql->from(App::core()->prefix() . 'comment');

        $count = $sql->select()->fInt();

        if (null !== $start && null !== $limit) {
            $sql->limit([$start, $limit]);
        }

        $sql->columns(
            [
                'comment_id',
                'comment_content',
            ],
            true // Re-use statement
        );

        $record = $sql->select();

        $sql = new UpdateStatement();
        $sql->from(App::core()->prefix() . 'comment');

        while ($record->fetch()) {
            $sql->set('comment_words = ' . $sql->quote(implode(' ', Text::splitWords($record->f('comment_content')))), true);
            $sql->where('comment_id = ' . $record->fInt('comment_id'), true);

            $sql->update();
        }

        return $start + $limit > $count ? null : $start + $limit;
    }
}
