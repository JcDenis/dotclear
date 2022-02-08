<?php
/**
 * @class Dotclear\Admin\Filter\UserFilter
 * @brief class for admin user list filters form
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

use function Dotclear\core;

use ArrayObject;

use Dotclear\Admin\Filter;
use Dotclear\Admin\Filters;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UserFilter extends Filter
{
    public function __construct()
    {
        parent::__construct('users');

        $filters = new arrayObject([
            Filters::getPageFilter(),
            Filters::getSearchFilter()
        ]);

        # --BEHAVIOR-- adminUserFilter
        core()->behaviors->call('adminUserFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }
}
