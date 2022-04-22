<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Berlin\Public;

// Dotclear\Theme\Berlin\Public\Prepend
use Dotclear\Helper\Html\Html;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

/**
 * Public prepend for theme Berlin.
 *
 * @ingroup  Theme Berlin
 */
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
                'navigation' => __('Main menu'),
            ]);
        });
    }
}
