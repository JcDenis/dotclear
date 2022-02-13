<?php
/**
 * @class Dotclear\Theme\Blowup\Public\Prepend
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeBlowup
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Blowup\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Theme\Blowup\Lib\BlowupConfig;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        if (dotclear()->blog->settings->system->theme == 'Blowup') {
            dotclear()->behavior()->add('publicHeadContent', [__CLASS__, 'behaviorPublicHeadContent']);
        }
    }

    public static function behaviorPublicHeadContent()
    {
        $config = new BlowupConfig();
        $url = $config->publicCssUrlHelper();
        if ($url) {
            echo '<link rel="stylesheet" href="' . $url . '" type="text/css" />';
        }
    }
}
