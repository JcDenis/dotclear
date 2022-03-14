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

use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        dotclear()->behavior()->add('publicHeadContent', function (): void {
            echo Html::jsJson('dotclear_berlin', [
                'show_menu'  => __('Show menu'),
                'hide_menu'  => __('Hide menu'),
                'navigation' => __('Main menu')
            ]);
        });
    }
}
