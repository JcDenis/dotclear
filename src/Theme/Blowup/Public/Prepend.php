<?php

declare(strict_types=1);

namespace Dotclear\Theme\Blowup\Public;

// Dotclear\Theme\Blowup\Public\Prepend
use Dotclear\App;
use Dotclear\Modules\ModulePrepend;
use Dotclear\Theme\Blowup\Common\BlowupConfig;

/**
 * Public prepend for theme Blowup.
 *
 * @ingroup  Theme Blowup
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        App::core()->behavior('templateAfterGetHead')->add(function (): void {
            $url = (new BlowupConfig())->publicCssUrlHelper();
            if ($url) {
                echo '<link rel="stylesheet" href="' . $url . '" type="text/css" />';
            }
        });
    }
}
