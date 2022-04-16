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

use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Text;
use Dotclear\Plugin\Maintenance\Admin\Lib\MaintenanceTask;

class MaintenanceTaskIndexposts extends MaintenanceTask
{
    protected $ajax  = true;
    protected $group = 'index';
    protected $limit = 500;
    protected $step_task;

    protected function init(): void
    {
        $this->name      = __('Search engine index');
        $this->task      = __('Index all entries for search engine');
        $this->step_task = __('Next');
        $this->step      = __('Indexing entry %d to %d.');
        $this->success   = __('Entries index done.');
        $this->error     = __('Failed to index entries.');

        $this->description = __('Index all entries in search engine index. This operation is necessary, after importing content in your blog, to use internal search engine, on public and private pages.');
    }

    public function execute(): int|bool
    {
        $this->code = $this->indexAllPosts($this->code, $this->limit);

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
     * Recreates entries search engine index.
     *
     * @param   int|null    $start  The start entry index
     * @param   int|null    $limit  The limit of entry to index
     *
     * @return  int|null    Sum of <var>$start</var> and <var>$limit</var>
     */
    public function indexAllPosts(?int $start = null, ?int $limit = null): ?int
    {
        $sql = new SelectStatement(__METHOD__);
        $count = $sql
            ->column($sql->count('post_id'))
            ->from(dotclear()->prefix . 'post')
            ->select()
            ->fInt();

        if (null !== $start && null !== $limit) {
            $sql->limit([$start, $limit]);
        }

        $rs = $sql
            ->columns([
                'post_id',
                'post_title',
                'post_excerpt_xhtml',
                'post_content_xhtml',
            ], true) # Re-use statement
            ->select();

        $sql = UpdateStatement::init(__METHOD__)
            ->from(dotclear()->prefix . 'post');

        while ($rs->fetch()) {
            $words = 
                $rs->f('post_title') . ' ' . 
                $rs->f('post_excerpt_xhtml') . ' ' . 
                $rs->f('post_content_xhtml');

            $sql
                ->set('post_words = ' . $sql->quote(implode(' ', Text::splitWords($words))), true)
                ->where('post_id = ' . $rs->fInt('post_id'), true)
                ->update();
        }

        return $start + $limit > $count ? null : $start + $limit;
    }
}
