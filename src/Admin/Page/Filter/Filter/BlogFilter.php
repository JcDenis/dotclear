<?php
/**
 * @class Dotclear\Admin\Page\Filter\Filter\BlogFilter
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

namespace Dotclear\Admin\Page\Filter\Filter;

use ArrayObject;

use Dotclear\Admin\Page\Filter\Filter;
use Dotclear\Admin\Page\Filter\Filters;
use Dotclear\Admin\Page\Filter\Filter\DefaultFilter;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class BlogFilter extends Filter
{
    public function __construct()
    {
        parent::__construct('blogs');

        $filters = new ArrayObject([
            Filters::getPageFilter(),
            Filters::getSearchFilter(),
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
        return (new DefaultFilter('status'))
            ->param('blog_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                dotclear()->combo()->getBlogStatusesCombo()
            ))
            ->prime(true);
    }
}