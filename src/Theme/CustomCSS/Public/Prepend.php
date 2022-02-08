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

use function Dotclear\core;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        if (core()->blog->settings->system->theme == 'CustomCSS') {
            core()->behaviors->add('publicHeadContent', [__CLASS__, 'behaviorPublicHeadContent']);
        }
    }

    public static function behaviorPublicHeadContent()
    {
        echo '<link rel="stylesheet" type="text/css" href="' . core()->blog->settings->system->public_url . '/custom_style.css" media="screen">' . "\n";
    }
}
