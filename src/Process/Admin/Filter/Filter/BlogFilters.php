<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\BlogFilters
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filters;
use Dotclear\Process\Admin\Filter\FilterStack;

/**
 * Admin blogs list filters form.
 *
 * @ingroup  Admin Blog Filter
 */
class BlogFilters extends Filters
{
    public function __construct()
    {
        parent::__construct(type: 'blogs', filters: new FilterStack(
            $this->getPageFilter(),
            $this->getSearchFilter(),
            $this->getBlogStatusFilter()
        ));
    }

    /**
     * Blog status select.
     */
    public function getBlogStatusFilter(): Filter
    {
        $filter = new Filter('status');
        $filter->param('blog_status', fn ($f) => (int) $f[0]);
        $filter->title(__('Status:'));
        $filter->options(array_merge(
            ['-' => ''],
            App::core()->combo()->getBlogStatusesCombo()
        ));
        $filter->prime(true);

        return $filter;
    }
}
