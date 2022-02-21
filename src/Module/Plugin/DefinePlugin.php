<?php
/**
 * @class Dotclear\Module\Plugin\DefinePlugin
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Plugin;

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\TraitDefine;
use Dotclear\Module\Plugin\TraitDefinePlugin;
use Dotclear\Module\Store\TraitDefineStore;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefinePlugin extends AbstractDefine
{
    use TraitDefinePlugin, TraitDefineStore, TraitDefine;

    protected $type = 'Plugin';

    protected function checkProperties(): void
    {
        $this->checkDefinePlugin();
        $this->checkDefineStore();
        $this->checkDefine();
    }
}
