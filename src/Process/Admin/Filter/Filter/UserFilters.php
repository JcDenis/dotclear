<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\UserFilters
use Dotclear\Process\Admin\Filter\Filters;
use Dotclear\Process\Admin\Filter\FilterStack;

/**
 * Admin users list filters form.
 *
 * @ingroup  Admin User Filter
 *
 * @since 2.20
 */
class UserFilters extends Filters
{
    public function __construct()
    {
        parent::__construct(id: 'users', filters: new FilterStack(
            $this->getPageFilter(),
            $this->getSearchFilter()
        ));
    }
}
