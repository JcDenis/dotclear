<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Blogroll\Common\BlogrollUrl;
use Dotclear\Plugin\Blogroll\Common\BlogrollWidgets;

/**
 * Public prepend for plugin Blogroll.
 *
 * \Dotclear\Plugin\Blogroll\Public\Prepend
 *
 * @ingroup  Plugin Blogroll
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new BlogrollWidgets();
        new BlogrollTemplate();
        new BlogrollUrl();
    }
}
