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
use ArrayObject;
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;

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

        $filters = new ArrayObject([
            $this->getPageFilter(),
            $this->getSearchFilter(),
            $this->getBlogStatusFilter(),
        ]);

        // --BEHAVIOR-- adminBlogFilter
        App::core()->behavior()->call('adminBlogFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
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
