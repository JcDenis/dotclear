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

use Dotclear\Plugin\Blogroll\Lib\BlogrollWidgets;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        # Widgets
        new BlogrollWidgets();

        # Templates
        $class = 'Dotclear\\Plugin\\Blogroll\\Lib\\BlogrollTemplate';
        dotclear()->template()->addValue('Blogroll', [$class, 'blogroll']);
        dotclear()->template()->addValue('BlogrollXbelLink', [$class, 'blogrollXbelLink']);

        # Url
        $class = 'Dotclear\\Plugin\\Blogroll\\Lib\\BlogrollUrl';
        dotclear()->url()->register('xbel', 'xbel', '^xbel(?:/?)$', [$class, 'xbel']);
    }
}
