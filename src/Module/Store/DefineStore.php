<?php
/**
 * @class Dotclear\Module\Store\DefineStore
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Store;

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\Store\TraitDefineStore;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefineStore extends AbstractDefine
{
    use TraitDefineStore;

    protected $type = 'Store';

    protected function checkModule(): void
    {
        $this->checkDefineStore();
    }
}
