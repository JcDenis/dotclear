<?php
/**
 * @class Dotclear\Plugin\Antispam\Public\Prepend
 * @brief Dotclear Plugin class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Antispam\Common\Antispam;
use Dotclear\Plugin\Antispam\Common\AntispamUrl;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        new Antispam();
        new AntispamUrl();
    }
}
