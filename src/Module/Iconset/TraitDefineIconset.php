<?php
/**
 * @class Dotclear\Module\Iconset\TraitDefineIconset
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset;

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\Store\TraitDefineStore;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefineIconset
{
    use TraitDefineStore;

    protected function checkDefineIconset(): void
    {
        # nothing to do for now

        $this->checkDefineStore();
    }
}
