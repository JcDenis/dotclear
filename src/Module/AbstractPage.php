<?php
/**
 * @class Dotclear\Module\AbstractPage
 * @brief Dotclear Module abstract admin page
 *
 * If exists, Default plugin Page must extends this class.
 * It provides automatic url registration and configuration links.
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Admin\Page\Page;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractPage extends Page
{
    # @see Dotclear\Admin\Page\Page for all others methods
}
