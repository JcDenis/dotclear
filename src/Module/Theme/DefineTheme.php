<?php
/**
 * @class Dotclear\Module\Theme\DefineTheme
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme;

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\TraitDefine;
use Dotclear\Module\Plugin\TraitDefinePlugin;
use Dotclear\Module\Store\TraitDefineStore;
use Dotclear\Module\Theme\TraitDefineTheme;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefineTheme extends AbstractDefine
{
    use TraitDefineTheme, TraitDefinePlugin, TraitDefineStore, TraitDefine;

    protected $type = 'Theme';

    protected function checkProperties(): void
    {
        $this->checkDefineTheme();
        $this->checkDefinePlugin();
        $this->checkDefineStore();
        $this->checkDefine();
    }
}
