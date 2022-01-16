<?php
/**
 * @class Dotclear\Module\Plugin\TraitModulesPlugin
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
use Dotclear\Module\Plugin\TraitDefinePlugin;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefinePlugin extends AbstractDefine
{
    use TraitDefinePlugin;

    protected $type = 'Plugin';

    protected function checkModule(): void
    {
        $this->checkDefinePlugin();
    }
}
