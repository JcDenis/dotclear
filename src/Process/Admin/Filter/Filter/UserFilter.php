<?php
/**
 * @note Dotclear\Process\Admin\Filter\Filter\UserFilter
 * @brief class for admin user list filters form
 *
 * @ingroup  Admin
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
        dotclear()->behavior()->call('adminUserFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }
}
