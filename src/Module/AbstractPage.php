<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

// Dotclear\Module\AbstractPage
use Dotclear\Process\Admin\Page\AbstractPage as AdminPage;

/**
 * Module abstract admin page.
 *
 * If exists, default plugin admin page must extend this class.
 * It provides automatic URL registration and configuration links.
 *
 * @note
 * Plugins default admin page namespace should be:
 * Dotclear\\Plugin\\Xxx\\Admin\\Handler
 *
 * @ingroup  Module Admin
 */
abstract class AbstractPage extends AdminPage
{
}
