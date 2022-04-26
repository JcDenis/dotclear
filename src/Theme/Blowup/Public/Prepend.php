<?php

declare(strict_types=1);

namespace Dotclear\Theme\Blowup\Public;

// Dotclear\Theme\Blowup\Public\Prepend
use Dotclear\App;
use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Theme\Blowup\Common\BlowupConfig;

/**
 * Public prepend for theme Blowup.
 *
 * @ingroup  Theme Blowup
 */
class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        if (!$this->isTheme()) {
            return;
        }

        App::core()->behavior()->add('publicHeadContent', function (): void {
            $url = (new BlowupConfig())->publicCssUrlHelper();
            if ($url) {
                echo '<link rel="stylesheet" href="' . $url . '" type="text/css" />';
            }
        });
    }
}
