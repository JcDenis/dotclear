<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Public;

// Dotclear\Plugin\Antispam\Public\Prepend
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Antispam\Common\Antispam;
use Dotclear\Plugin\Antispam\Common\AntispamUrl;

/**
 * Public prepend for plugin Antispam.
 *
 * @ingroup  Plugin Antispam
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        new Antispam();
        new AntispamUrl();
    }
}
