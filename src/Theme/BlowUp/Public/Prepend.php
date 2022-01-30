<?php
/**
 * @class Dotclear\Theme\BlowUp\Public\Prepend
 * @brief Dotclear Theme class
 *
 * @package Dotclear
 * @subpackage ThemeBlowUp
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\BlowUp\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Theme\BlowUp\Lib\BlowUpConfig;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        if (dcCore()->blog->settings->system->theme == 'BlowUp') {
            dcCore()->behaviors->add('publicHeadContent', [__CLASS__, 'behaviorPublicHeadContent']);
        }
    }

    public static function behaviorPublicHeadContent()
    {
        $config = new BlowUpConfig();
        $url = $config->publicCssUrlHelper();
        if ($url) {
            echo '<link rel="stylesheet" href="' . $url . '" type="text/css" />';
        }
    }
}
