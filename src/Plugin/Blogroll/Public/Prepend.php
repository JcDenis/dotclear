<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Public;

// Dotclear\Plugin\Blogroll\Public\Prepend
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Blogroll\Common\BlogrollUrl;
use Dotclear\Plugin\Blogroll\Common\BlogrollWidgets;

/**
 * Public prepend for plugin Blogroll.
 *
 * @ingroup  Plugin Blogroll
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        new BlogrollWidgets();
        new BlogrollTemplate();
        new BlogrollUrl();
    }
}
