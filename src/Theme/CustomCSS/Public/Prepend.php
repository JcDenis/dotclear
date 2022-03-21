<?php
/**
 * @class Dotclear\Theme\CustomCSS\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage ThemeCustomCSS
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\CustomCSS\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        dotclear()->behavior()->add('publicHeadContent', function(): void {
            echo
                '<link rel="stylesheet" type="text/css" href="' .
                dotclear()->blog()->public_url .
                '/custom_style.css" media="screen">' . "\n";
        });
    }
}
