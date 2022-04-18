<?php
/**
 * @note Dotclear\Module\AbstractPage
 * @brief module abstract admin page.
 *
 * If exists, Default plugin page must extends this class.
 * It provides automatic url registration and configuration links.
 *
 * note: plugins default admin page namespace should be:
 * Dotclear\Plugins\Xxx\Admin\Handler
 *
 * @ingroup  Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Process\Admin\Page\AbstractPage as AdminPage;

abstract class AbstractPage extends AdminPage
{
    // @see Dotclear\Process\Admin\Page\AbstarctPage for all others methods
}
