<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\BlogFilter
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\FiltersStack;

/**
 * Admin blogs list filters form.
 *
 * @ingroup  Admin Blog Filter
 */
class BlogFilter extends Filter
{
    public function __construct()
    {
        parent::__construct('blogs');

        $fs = new FiltersStack(
            $this->getPageFilter(),
            $this->getSearchFilter(),
            $this->getBlogStatusFilter()
        );

        // --BEHAVIOR-- adminBlogFilter, FiltersStack
        App::core()->behavior()->call('adminBlogFilter', $fs);

        $this->addStack($fs);
    }

    /**
     * Blog status select.
     */
    public function getBlogStatusFilter(): DefaultFilter
    {
        return DefaultFilter::init('status')
            ->param('blog_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                App::core()->combo()->getBlogStatusesCombo()
            ))
            ->prime(true)
        ;
    }
}
