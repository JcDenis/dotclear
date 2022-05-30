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
        parent::__construct(id: 'blogs', filters: new FilterStack(
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
        return new Filter(
            id: 'status',
            title: __('Status:'),
            params: [
                ['blog_status', fn ($f) => (int) $f[0]],
            ],
            options: array_merge(
                ['-' => ''],
                App::core()->combo()->getBlogStatusesCombo()
            ),
            prime: true
        );
    }
}
