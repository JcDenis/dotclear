<?php
/**
 * @class Dotclear\Process\Admin\Filter\Filter\BlogFilter
 * @brief class for admin blog list filters form
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

use ArrayObject;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filter\DefaultFilter;

class BlogFilter extends Filter
{
    public function __construct()
    {
        parent::__construct('blogs');

        $filters = new ArrayObject([
            $this->getPageFilter(),
            $this->getSearchFilter(),
            $this->getBlogStatusFilter()
        ]);

        # --BEHAVIOR-- adminBlogFilter
        dotclear()->behavior()->call('adminBlogFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }

    /**
     * Blog status select
     */
    public function getBlogStatusFilter(): DefaultFilter
    {
        return DefaultFilter::init('status')
            ->param('blog_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getBlogStatusesCombo()
            ))
            ->prime(true);
    }
}
