<?php
/**
 * @class Dotclear\Module\AbstractPage
 * @brief Dotclear Module abstract admin page
 *
 * If exists, Default plugin page must extends this class.
 * It provides automatic url registration and configuration links.
 * 
 * note: plugins default admin page namespace should be:
 * Dotclear\Plugins\Xxx\Admin\Handler
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Process\Admin\Page\Page;

abstract class AbstractPage extends Page
{
    # @see Dotclear\Process\Admin\Page\Page for all others methods
}
