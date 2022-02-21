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
use Dotclear\Module\TraitDefine;
use Dotclear\Module\Store\TraitDefineStore;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefineStore extends AbstractDefine
{
    use TraitDefineStore, TraitDefine;

    protected $type = 'Store';

    protected function checkProperties(): void
    {
        $this->checkDefineStore();
        $this->checkDefine();
    }
}
