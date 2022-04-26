<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\UserFilter
use ArrayObject;
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;

/**
 * Admin users list filters form.
 *
 * @ingroup  Admin User Filter
 *
 * @since 2.20
 */
class UserFilter extends Filter
{
    public function __construct()
    {
        parent::__construct('users');

        $filters = new arrayObject([
            $this->getPageFilter(),
            $this->getSearchFilter(),
        ]);

        // --BEHAVIOR-- adminUserFilter
        App::core()->behavior()->call('adminUserFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }
}
