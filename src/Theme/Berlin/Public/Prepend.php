<?php
/**
 * @class Dotclear\Theme\Berlin\Public\Prepend
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeBerlin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Berlin\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Core\Utils;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        if (dotclear()->blog->settings->system->theme == 'Berlin') {
            dotclear()->behavior()->add('publicHeadContent', [__CLASS__, 'behaviorPublicHeadContent']);
        }
    }

    public static function behaviorPublicHeadContent()
    {
        echo Utils::jsJson('dotclear_berlin', [
            'show_menu'  => __('Show menu'),
            'hide_menu'  => __('Hide menu'),
            'navigation' => __('Main menu')
        ]);
    }
}
