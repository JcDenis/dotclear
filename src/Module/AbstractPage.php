<?php
/**
 * @class Dotclear\Module\AbstractConfig
 * @brief Dotclear Module abstract Config
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

use Dotclear\Core\Core;
use Dotclear\Admin\Page;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractPage extends Page
{
    # @see Dotclear\Admin\Page for all others methods
}
