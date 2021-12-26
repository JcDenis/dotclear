<?php
/**
 * @class Dotclear\Admin\Filter\BlogFilter
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

namespace Dotclear\Admin\Filter;

use Dotclear\Core\Core;

use Dotclear\Admin\Combos;
use Dotclear\Admin\Filter;
use Dotclear\Admin\Filters;
use Dotclear\Admin\Filter\DefaultFilter;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class BlogFilter extends Filter
{
    public function __construct(Core $core)
    {
        parent::__construct($core, 'blogs');

        $filters = new \arrayObject([
            Filters::getPageFilter(),
            Filters::getSearchFilter(),
            $this->getBlogStatusFilter()
        ]);

        # --BEHAVIOR-- adminBlogFilter
        $core->behaviors->call('adminBlogFilter', $core, $filters);

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
                Combos::getBlogStatusesCombo()
            ))
            ->prime(true);
    }
}
