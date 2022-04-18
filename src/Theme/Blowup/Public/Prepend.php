<?php
/**
 * @note Dotclear\Theme\Blowup\Public\Prepend
 * @brief Dotclear Theme class
 *
 * @ingroup  ThemeBlowup
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\Blowup\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Theme\Blowup\Common\BlowupConfig;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        dotclear()->behavior()->add('publicHeadContent', function (): void {
            $url = (new BlowupConfig())->publicCssUrlHelper();
            if ($url) {
                echo '<link rel="stylesheet" href="' . $url . '" type="text/css" />';
            }
        });
    }
}
