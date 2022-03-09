<?php
/**
 * @class Dotclear\Plugin\Blogroll\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
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
use Dotclear\Plugin\Blogroll\Public\BlogrollTemplate;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

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
